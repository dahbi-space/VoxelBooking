<?php

declare(strict_types=1);

/**
 * Migration 018: Create rate_limits table.
 *
 * High-volume throwaway table for per-IP rate limiting.
 * Uses auto-increment integer ID (not ULID) for performance.
 * Cleaned up by cron (entries older than 1 hour deleted).
 */
return [
    "CREATE TABLE IF NOT EXISTS `rate_limits` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `ip` VARCHAR(45) NOT NULL,
        `endpoint_group` VARCHAR(50) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `rate_limits_lookup` (`ip`, `endpoint_group`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
