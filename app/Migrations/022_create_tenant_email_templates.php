<?php

declare(strict_types=1);

/**
 * Migration 022: Create tenant_email_templates table.
 *
 * Per PRD §VII — tenant-scoped transactional email customization.
 *
 * The system owns the email layout (branded header, summary card, footer).
 * Tenants customize copy per email type: subject line, heading, body intro,
 * body outro, and CTA label. When a tenant-specific template row does not
 * exist for a given type, the system defaults are used.
 *
 * Supported types align with email_log.type:
 *   confirmation, reminder, cancellation, reschedule_confirmation,
 *   staff_notification, approval_request, approval_confirmed
 *
 * All text columns support i18n placeholders, e.g.:
 *   {customer_name}, {service_name}, {booking_date}, {booking_time},
 *   {staff_name}, {business_name}, {booking_url}
 */
return [
    "CREATE TABLE IF NOT EXISTS `tenant_email_templates` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `type` VARCHAR(30) NOT NULL COMMENT 'Valid: confirmation, reminder, cancellation, reschedule_confirmation, staff_notification, approval_request, approval_confirmed',
        `subject` VARCHAR(255) NULL DEFAULT NULL,
        `heading` VARCHAR(255) NULL DEFAULT NULL,
        `body_intro` TEXT NULL DEFAULT NULL,
        `body_outro` TEXT NULL DEFAULT NULL,
        `cta_label` VARCHAR(100) NULL DEFAULT NULL,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tenant_email_templates_tenant_type_unique` (`tenant_id`, `type`),
        CONSTRAINT `tenant_email_templates_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
