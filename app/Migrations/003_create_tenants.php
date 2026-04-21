<?php

declare(strict_types=1);

/**
 * Migration 003: Create tenants table.
 *
 * Each tenant = one business with its own booking page and settings.
 * Full schema per PRD §IV (lines 812-858).
 */
return [
    "CREATE TABLE IF NOT EXISTS `tenants` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `slug` VARCHAR(100) NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) NULL DEFAULT NULL,
        `timezone` VARCHAR(100) NOT NULL DEFAULT 'UTC',
        `booking_pattern` VARCHAR(20) NOT NULL COMMENT 'Valid: timeslot, resource, capacity, event',
        `locale` VARCHAR(10) NOT NULL DEFAULT 'en',
        `locale_override` VARCHAR(10) NULL DEFAULT NULL,
        `week_start` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0=Sun, 1=Mon, 6=Sat. Seeded from operator defaults',
        `time_format` VARCHAR(3) NOT NULL DEFAULT '24h' COMMENT '12h or 24h. Seeded from operator defaults',
        `date_format` VARCHAR(10) NOT NULL DEFAULT 'Y-m-d' COMMENT 'PHP date() format. Seeded from operator defaults',
        `number_format` VARCHAR(10) NOT NULL DEFAULT 'period' COMMENT 'period, comma, or space. Seeded from operator defaults',
        `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
        `brand_color` VARCHAR(7) NOT NULL DEFAULT '#2563EB',
        `brand_color_text` VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
        `show_powered_by` TINYINT(1) NOT NULL DEFAULT 1,
        `logo_path` VARCHAR(500) NULL DEFAULT NULL,
        `cover_image_path` VARCHAR(500) NULL DEFAULT NULL,
        `booking_page_heading` VARCHAR(255) NULL DEFAULT NULL,
        `booking_page_description` TEXT NULL DEFAULT NULL,
        `confirmation_message` TEXT NULL DEFAULT NULL,
        `cancellation_policy` TEXT NULL DEFAULT NULL,
        `allow_cancellation` TINYINT(1) NOT NULL DEFAULT 1,
        `cancellation_hours_before` INT NOT NULL DEFAULT 24,
        `allow_rescheduling` TINYINT(1) NOT NULL DEFAULT 1,
        `rescheduling_hours_before` INT NOT NULL DEFAULT 24,
        `min_advance_hours` INT NOT NULL DEFAULT 1,
        `max_advance_days` INT NOT NULL DEFAULT 90,
        `slot_duration_minutes` INT NOT NULL DEFAULT 30,
        `buffer_minutes` INT NOT NULL DEFAULT 0,
        `max_bookings_per_customer_per_day` INT NOT NULL DEFAULT 3,
        `booking_requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
        `require_phone` TINYINT(1) NOT NULL DEFAULT 0,
        `custom_fields` JSON NULL DEFAULT NULL,
        `notification_email` VARCHAR(255) NULL DEFAULT NULL,
        `notify_on_booking` TINYINT(1) NOT NULL DEFAULT 1,
        `notify_on_cancellation` TINYINT(1) NOT NULL DEFAULT 1,
        `send_reminders` TINYINT(1) NOT NULL DEFAULT 1,
        `reminder_hours_before` INT NOT NULL DEFAULT 24,
        `requires_consent` TINYINT(1) NOT NULL DEFAULT 1,
        `privacy_policy_url` VARCHAR(500) NULL DEFAULT NULL,
        `consent_text` VARCHAR(500) NULL DEFAULT NULL,
        `data_retention_months` INT NOT NULL DEFAULT 24,
        `allowed_embed_domains` TEXT NULL DEFAULT NULL,
        `embed_button_position` VARCHAR(20) NOT NULL DEFAULT 'bottom-right' COMMENT 'Valid: bottom-right, bottom-left',
        `embed_button_label` VARCHAR(50) NOT NULL DEFAULT 'Book Now',
        `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'Valid: active, paused, archived',
        `meta` JSON NULL DEFAULT NULL COMMENT 'Sparse extension data for future features',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tenants_slug_unique` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
