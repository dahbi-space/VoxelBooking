<?php

declare(strict_types=1);

/**
 * Migration 001: Create settings table.
 *
 * The settings table is the first table created because:
 * 1. The Migrator uses it to track db_version
 * 2. The installation wizard stores configuration here
 * 3. The App kernel checks installed_at here
 */
return [
    "CREATE TABLE IF NOT EXISTS `settings` (
        `key` VARCHAR(100) NOT NULL,
        `value` TEXT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
