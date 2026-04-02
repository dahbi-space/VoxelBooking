<?php

declare(strict_types=1);

/**
 * Migration 020: Create bookings table.
 *
 * Per PRD §IV (data model) — per-tenant booking records.
 * Consent fields (consent_given_at, consent_text_shown) are GDPR evidence
 * and must NEVER be anonymized. Customer-facing PII fields (notes,
 * custom_field_data) are cleared during anonymization.
 */
return [
    "CREATE TABLE IF NOT EXISTS `bookings` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `booking_pattern` VARCHAR(20) NOT NULL COMMENT 'Valid: timeslot, resource, capacity, event',
        `service_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `staff_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `resource_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `event_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `customer_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `start_datetime` DATETIME NOT NULL,
        `end_datetime` DATETIME NOT NULL,
        `party_size` INT NOT NULL DEFAULT 1,
        `status` VARCHAR(20) NOT NULL DEFAULT 'confirmed' COMMENT 'Valid: pending, confirmed, cancelled, rescheduled, completed, no_show',
        `rescheduled_to_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `notes` TEXT NULL DEFAULT NULL,
        `internal_notes` TEXT NULL DEFAULT NULL,
        `custom_field_data` JSON NULL DEFAULT NULL,
        `consent_given_at` DATETIME NULL DEFAULT NULL,
        `consent_text_shown` VARCHAR(500) NULL DEFAULT NULL,
        `customer_timezone` VARCHAR(100) NULL DEFAULT NULL,
        `confirmation_sent_at` DATETIME NULL DEFAULT NULL,
        `reminder_sent_at` DATETIME NULL DEFAULT NULL,
        `cancelled_at` DATETIME NULL DEFAULT NULL,
        `cancellation_reason` VARCHAR(255) NULL DEFAULT NULL,
        `source` VARCHAR(10) NOT NULL DEFAULT 'web' COMMENT 'Valid: web, admin, api, embed',
        `meta` JSON NULL DEFAULT NULL COMMENT 'Sparse extension data for future features',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `bookings_tenant_pattern_status_idx` (`tenant_id`, `booking_pattern`, `status`),
        KEY `bookings_tenant_status_start_idx` (`tenant_id`, `status`, `start_datetime`),
        KEY `bookings_customer_id_idx` (`customer_id`),
        KEY `bookings_staff_start_idx` (`staff_id`, `start_datetime`),
        KEY `bookings_resource_start_idx` (`resource_id`, `start_datetime`),
        KEY `bookings_event_id_idx` (`event_id`),
        CONSTRAINT `bookings_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
        CONSTRAINT `bookings_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

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

