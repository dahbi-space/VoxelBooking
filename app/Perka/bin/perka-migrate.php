<?php

declare(strict_types=1);

/**
 * Perka migration CLI (option B).
 *
 * Thin, standalone entrypoint for CI/CD and manual deploys. It boots the core
 * container only far enough to get DB access (via app/bootstrap.php, which
 * loads the autoloader + .env and initialises the logger), then runs Perka
 * migrations through the single guarded run method (PerkaMigrator::run(), which
 * holds the non-blocking advisory lock).
 *
 * Lives entirely inside app/Perka — deleting the folder removes it. Introduces
 * no routes, no menu, and no core changes.
 *
 * Usage:   php app/Perka/bin/perka-migrate.php
 * Exit:    0 = applied / nothing pending / skipped-because-locked (all benign)
 *          1 = a migration failed (CI should catch this)
 *
 * @noinspection PhpIncludeInspection
 */

use App\Perka\Shared\PerkaMigrator;

// app/Perka/bin/ -> project root is three directories up.
$root = dirname(__DIR__, 3);

$bootstrap = $root . '/app/bootstrap.php';
if (!is_file($bootstrap)) {
    fwrite(STDERR, "perka-migrate: cannot locate app/bootstrap.php at {$bootstrap}\n");
    exit(1);
}

// Boots env + autoloader + logger. Returns the core App (unused here).
require $bootstrap;

$migrator = new PerkaMigrator($root . '/app/Perka/Modules');

try {
    $applied = $migrator->run();

    if ($applied === PerkaMigrator::SKIPPED_LOCKED) {
        fwrite(STDOUT, "perka-migrate: another migration run is in progress — skipped.\n");
        exit(0);
    }

    if ($applied === 0) {
        fwrite(STDOUT, "perka-migrate: nothing pending.\n");
        exit(0);
    }

    fwrite(STDOUT, "perka-migrate: applied {$applied} migration(s).\n");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'perka-migrate: FAILED — ' . $e->getMessage() . "\n");
    exit(1);
}
