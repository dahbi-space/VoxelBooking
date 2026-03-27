<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Sequential migration runner.
 *
 * Reads migration files from app/Migrations/. Compares file sequence numbers
 * against db_version in settings. Runs pending migrations in order.
 */
final class Migrator
{
    private string $migrationsPath;

    public function __construct(string $migrationsPath)
    {
        $this->migrationsPath = rtrim($migrationsPath, '/');
    }

    /**
     * Run all pending migrations.
     *
     * @return int Number of migrations executed
     */
    public function migrate(): int
    {
        $currentVersion = $this->getCurrentVersion();
        $files = $this->getPendingMigrations($currentVersion);

        $count = 0;

        foreach ($files as $file) {
            $sequence = $this->getSequenceNumber($file);
            $statements = require $this->migrationsPath . '/' . $file;

            if (!is_array($statements)) {
                throw new \RuntimeException("Migration {$file} must return an array of SQL statements");
            }

            $pdo = Database::pdo();

            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }

            $this->updateVersion($sequence);
            $count++;

            Logger::info("Migration {$file} executed (version {$sequence})");
        }

        return $count;
    }

    /**
     * Get the current database version.
     */
    public function getCurrentVersion(): int
    {
        try {
            $result = Database::query(
                "SELECT `value` FROM `settings` WHERE `key` = 'db_version'"
            );

            return (int) ($result[0]['value'] ?? 0);
        } catch (\PDOException) {
            // settings table doesn't exist yet
            return 0;
        }
    }

    /**
     * Check if there are pending migrations.
     */
    public function hasPending(): bool
    {
        $currentVersion = $this->getCurrentVersion();
        $files = $this->getPendingMigrations($currentVersion);

        return count($files) > 0;
    }

    /**
     * Get migration files with sequence > current version, sorted ascending.
     *
     * @return string[]
     */
    private function getPendingMigrations(int $currentVersion): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = scandir($this->migrationsPath);

        if ($files === false) {
            return [];
        }

        $migrations = [];

        foreach ($files as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }

            $seq = $this->getSequenceNumber($file);

            if ($seq > $currentVersion) {
                $migrations[$seq] = $file;
            }
        }

        ksort($migrations);

        return array_values($migrations);
    }

    /**
     * Extract the sequence number from a migration filename.
     * Expected format: 001_create_settings.php → 1
     */
    private function getSequenceNumber(string $filename): int
    {
        preg_match('/^(\d+)_/', $filename, $matches);

        return (int) ($matches[1] ?? 0);
    }

    private function updateVersion(int $version): void
    {
        Database::execute(
            "INSERT INTO `settings` (`key`, `value`, `updated_at`)
             VALUES ('db_version', ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()",
            [(string) $version, (string) $version]
        );
    }
}
