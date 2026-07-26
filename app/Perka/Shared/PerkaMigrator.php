<?php

declare(strict_types=1);

namespace App\Perka\Shared;

use App\Engine\Database;
use App\Engine\Logger;

/**
 * Minimal, Perka-owned migration runner.
 *
 * Why this exists instead of the core Migrator:
 * The core Migrator (app/Engine/Migrator.php) tracks progress with a single
 * integer watermark in `settings.db_version` and runs any file whose sequence
 * number is greater than it. Sharing that watermark with Perka would let a
 * future upstream core migration numbered below Perka's band be silently
 * skipped on update — breaking the "easy upstream merge" and "clean removal"
 * goals. So Perka keeps its own applied-list and never reads or writes
 * `settings.db_version`.
 *
 * Design:
 * - Discovers files at app/Perka/Modules/{Module}/Migrations/*.php.
 * - Reuses the CORE migration file format: each file `return`s an array of
 *   SQL statements.
 * - Records what has run in `perka_migrations` as an applied-LIST (one row per
 *   migration), keyed by the module-relative path ("PublicProfile/001_...php")
 *   so identically-numbered files in different modules never collide.
 * - Idempotent (not transactional): already-applied files are skipped. There is
 *   no wrapping transaction because MySQL auto-commits DDL, so safety comes from
 *   each file being one idempotent unit plus the applied-list row being written
 *   only AFTER the SQL succeeds; a mid-run failure leaves it unrecorded and it
 *   is safely retried on the next run.
 *
 * Removability: `perka_migrations` is itself a perka_ table with no foreign
 * keys, so it never blocks anything; deleting app/Perka removes this runner
 * entirely and the leftover perka_ tables drop harmlessly with their tenants.
 */
final class PerkaMigrator
{
    /** Application-level advisory lock name (connection-scoped in MySQL). */
    private const LOCK_NAME = 'perka_migrate';

    /** Sentinel returned by run() when the advisory lock was not acquired. */
    public const SKIPPED_LOCKED = -1;

    private string $modulesPath;

    /**
     * @param string $modulesPath Absolute path to app/Perka/Modules
     */
    public function __construct(string $modulesPath)
    {
        $this->modulesPath = rtrim($modulesPath, '/');
    }

    /**
     * Guarded entry point used by EVERY trigger (CLI now; admin page later).
     *
     * Wraps migrate() in a non-blocking MySQL advisory lock so two concurrent
     * runners can never race to apply the same migration (which would surface
     * as the duplicate-key error on `perka_migrations`). If the lock is already
     * held, this is a clean no-op — it logs and returns SKIPPED_LOCKED rather
     * than racing or throwing.
     *
     * The lock is released in a finally block so a caught exception mid-run
     * still frees it; MySQL also auto-releases on connection death (the crash
     * case). GET_LOCK/RELEASE_LOCK and every migrate() query share the same
     * pooled Database connection, which is what makes the lock effective.
     *
     * @return int Number applied this run, or SKIPPED_LOCKED if another run held the lock
     */
    public function run(): int
    {
        if (!$this->acquireLock()) {
            Logger::info('Perka migration skipped: another migration run in progress');
            return self::SKIPPED_LOCKED;
        }

        try {
            return $this->migrate();
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Run all pending Perka migrations across every module.
     *
     * @return int Number of migrations executed this run
     */
    public function migrate(): int
    {
        $this->ensureRegistryTable();

        $applied = $this->appliedList();
        $count = 0;

        foreach ($this->discover() as $identifier => $file) {
            if (isset($applied[$identifier])) {
                continue;
            }

            $statements = require $file;

            if (!is_array($statements)) {
                throw new \RuntimeException(
                    "Perka migration {$identifier} must return an array of SQL statements"
                );
            }

            // No explicit transaction: MySQL implicitly commits on DDL
            // (CREATE TABLE), which would both defeat a wrapper and make its
            // later commit() throw "no active transaction". Per the approved
            // policy, safety comes from idempotency instead — each file is one
            // idempotent unit (CREATE TABLE IF NOT EXISTS / idempotent DML) and
            // the applied-list row is written AFTER the SQL, so a mid-run
            // failure simply leaves the migration unrecorded and safely retried.
            foreach ($statements as $sql) {
                Database::execute($sql);
            }

            Database::execute(
                'INSERT INTO `perka_migrations` (`migration`) VALUES (?)',
                [$identifier]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Whether any Perka migration has not yet been applied.
     */
    public function hasPending(): bool
    {
        return $this->pendingCount() > 0;
    }

    /**
     * Number of discovered migrations not yet applied.
     */
    public function pendingCount(): int
    {
        $this->ensureRegistryTable();
        $applied = $this->appliedList();

        $count = 0;
        foreach (array_keys($this->discover()) as $identifier) {
            if (!isset($applied[$identifier])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Create the applied-list registry if it does not exist.
     *
     * Not a counter: one row per applied migration. No foreign keys, so it can
     * never block core deletions.
     */
    private function ensureRegistryTable(): void
    {
        Database::execute(
            "CREATE TABLE IF NOT EXISTS `perka_migrations` (
                `migration` VARCHAR(255) NOT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * @return array<string, true> Map of applied migration identifiers.
     */
    private function appliedList(): array
    {
        $rows = Database::query('SELECT `migration` FROM `perka_migrations`');

        $applied = [];
        foreach ($rows as $row) {
            $applied[$row['migration']] = true;
        }

        return $applied;
    }

    /**
     * Discover module migrations, sorted by identifier for deterministic order.
     *
     * @return array<string, string> identifier ("Module/NNN_name.php") => absolute path
     */
    private function discover(): array
    {
        if (!is_dir($this->modulesPath)) {
            return [];
        }

        $found = [];

        foreach (glob($this->modulesPath . '/*/Migrations/*.php') ?: [] as $path) {
            // identifier = "{Module}/{filename}"
            $module = basename(dirname(dirname($path)));
            $identifier = $module . '/' . basename($path);
            $found[$identifier] = $path;
        }

        ksort($found);

        return $found;
    }

    /**
     * Try to grab the advisory lock without blocking.
     * GET_LOCK returns 1 if acquired, 0 on timeout, NULL on error.
     */
    private function acquireLock(): bool
    {
        $rows = Database::query('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK_NAME]);

        return (int) ($rows[0]['acquired'] ?? 0) === 1;
    }

    /**
     * Release the advisory lock. Safe to call on the same connection that held
     * it; a no-op if this connection did not own the lock.
     */
    private function releaseLock(): void
    {
        Database::query('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
    }
}
