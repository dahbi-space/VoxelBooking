<?php

declare(strict_types=1);

/**
 * Migration 006: Create staff table.
 *
 * Per PRD §IV — staff members for timeslot tenants.
 * Each staff member belongs to one tenant and can be linked to services.
 */
return [
    "CREATE TABLE IF NOT EXISTS `staff` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) NULL DEFAULT NULL,
        `title` VARCHAR(255) NULL DEFAULT NULL,
        `bio` TEXT NULL DEFAULT NULL,
        `avatar_path` VARCHAR(500) NULL DEFAULT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `meta` JSON NULL DEFAULT NULL COMMENT 'Sparse extension data for future features',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `staff_tenant_email_unique` (`tenant_id`, `email`),
        KEY `staff_tenant_active_idx` (`tenant_id`, `is_active`, `sort_order`),
        CONSTRAINT `staff_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
