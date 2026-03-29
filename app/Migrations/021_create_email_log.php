<?php

declare(strict_types=1);

/**
 * Migration 021: Create email_log table.
 *
 * Per PRD §IV (data model) — tracks all outbound emails.
 * to_email is PII and must be SHA-256 hashed during anonymization.
 */
return [
    "CREATE TABLE IF NOT EXISTS `email_log` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `booking_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `type` VARCHAR(30) NOT NULL COMMENT 'Valid: confirmation, reminder, cancellation, reschedule_confirmation, staff_notification, approval_request, approval_confirmed, privacy_export, privacy_deletion, operator_notification, test',
        `to_email` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(500) NOT NULL,
        `status` VARCHAR(10) NOT NULL DEFAULT 'sent' COMMENT 'Valid: sent, failed',
        `error` TEXT NULL DEFAULT NULL,
        `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `email_log_tenant_idx` (`tenant_id`),
        KEY `email_log_booking_idx` (`booking_id`),
        KEY `email_log_sent_at_idx` (`sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
