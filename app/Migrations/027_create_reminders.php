<?php

declare(strict_types=1);

/**
 * Migration 027: Create reminders table.
 *
 * Per PRD §VII — booking reminder scheduling.
 *
 * A reminder row is inserted when a booking is created if the tenant
 * has send_reminders = 1. The cron job queries unsent reminders whose
 * scheduled_at has passed, sends the email, and marks them sent.
 */
return [
    "CREATE TABLE IF NOT EXISTS `reminders` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `booking_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `scheduled_at` DATETIME NOT NULL COMMENT 'When the reminder should be sent',
        `sent_at` DATETIME NULL DEFAULT NULL,
        `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
        `last_error` VARCHAR(500) NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `reminders_pending_idx` (`sent_at`, `scheduled_at`),
        KEY `reminders_booking_idx` (`booking_id`),
        CONSTRAINT `reminders_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
        CONSTRAINT `reminders_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
