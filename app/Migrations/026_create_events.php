<?php

declare(strict_types=1);

/**
 * Migration 026: Create events table.
 *
 * Per Phase E roadmap — event-pattern tenants (yoga classes, workshops,
 * wine tastings, recurring group sessions).
 *
 * Events can be one-off or recurring. Recurring events use RRULE strings
 * (RFC 5545) with optional exception dates stored as JSON.
 *
 * Waitlist support: allow_waitlist + waitlist_max control whether
 * full events accept additional registrations with status 'waitlisted'.
 */
return [
    "CREATE TABLE IF NOT EXISTS `events` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(200) NOT NULL,
        `description` TEXT NULL DEFAULT NULL,
        `location` VARCHAR(255) NULL DEFAULT NULL,
        `price` DECIMAL(10, 2) NULL DEFAULT NULL,
        `max_participants` INT NOT NULL DEFAULT 20,
        `start_datetime` DATETIME NOT NULL,
        `end_datetime` DATETIME NOT NULL,
        `is_recurring` TINYINT(1) NOT NULL DEFAULT 0,
        `rrule` VARCHAR(500) NULL DEFAULT NULL COMMENT 'RFC 5545 RRULE string for recurring events',
        `exception_dates` JSON NULL DEFAULT NULL COMMENT 'Array of Y-m-d dates to skip',
        `allow_waitlist` TINYINT(1) NOT NULL DEFAULT 0,
        `waitlist_max` INT NOT NULL DEFAULT 5,
        `cover_image_path` VARCHAR(500) NULL DEFAULT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `events_tenant_active_idx` (`tenant_id`, `is_active`, `start_datetime`),
        CONSTRAINT `events_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
