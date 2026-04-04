<?php

declare(strict_types=1);

/**
 * Migration 027: Add calendar-personalization columns to tenants.
 *
 * - week_start:  Override locale default (0=Sunday, 1=Monday, …, 6=Saturday).
 *                NULL = follow locale default. Non-null = explicit tenant preference.
 * - time_format: Override locale default ('12h' or '24h').
 *                NULL = follow locale default. Non-null = explicit tenant preference.
 */
return [
    "ALTER TABLE `tenants`
        ADD COLUMN `week_start`   TINYINT(1) NULL DEFAULT NULL COMMENT 'Week start override (0=Sun,1=Mon,...,6=Sat). NULL=locale default' AFTER `locale_override`,
        ADD COLUMN `time_format`  VARCHAR(3) NULL DEFAULT NULL COMMENT 'Time format override: 12h or 24h. NULL=locale default' AFTER `week_start`",
];
