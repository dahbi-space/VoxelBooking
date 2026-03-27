<?php

declare(strict_types=1);

/**
 * Migration 019: Create customers table.
 *
 * Per PRD §IV (data model) — per-tenant customer records.
 * Supports anonymization: name, email, phone, notes are PII fields.
 */
return [
    "CREATE TABLE IF NOT EXISTS `customers` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) NULL DEFAULT NULL,
        `notes` TEXT NULL DEFAULT NULL,
        `booking_count` INT NOT NULL DEFAULT 0,
        `last_booking_at` DATETIME NULL DEFAULT NULL,
        `is_anonymized` TINYINT(1) NOT NULL DEFAULT 0,
        `anonymized_at` DATETIME NULL DEFAULT NULL,
        `deletion_requested_at` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `customers_tenant_email_unique` (`tenant_id`, `email`),
        KEY `customers_tenant_id_idx` (`tenant_id`),
        KEY `customers_anonymized_idx` (`is_anonymized`),
        KEY `customers_deletion_pending_idx` (`deletion_requested_at`, `is_anonymized`),
        CONSTRAINT `customers_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
