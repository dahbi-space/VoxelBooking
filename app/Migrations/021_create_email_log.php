<?php

declare(strict_types=1);

/**
 * Migration 007: Create email_log table.
 *
 * Per PRD §IV (data model) — tracks all outbound emails.
 * to_email is PII and must be SHA-256 hashed during anonymization.
 */
return [
    "CREATE TABLE IF NOT EXISTS `email_log` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `booking_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `type` ENUM('confirmation','reminder','cancellation','reschedule','staff_notification') NOT NULL,
        `to_email` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(500) NOT NULL,
        `status` ENUM('sent','failed') NOT NULL DEFAULT 'sent',
        `error` TEXT NULL DEFAULT NULL,
        `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `email_log_tenant_idx` (`tenant_id`),
        KEY `email_log_booking_idx` (`booking_id`),
        KEY `email_log_sent_at_idx` (`sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
