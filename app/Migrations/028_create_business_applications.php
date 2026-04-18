<?php

declare(strict_types=1);

/**
 * Migration 028: Create business_applications table.
 *
 * Stores access requests submitted via the public landing page
 * for operator review and approval.
 */
return [
    "CREATE TABLE IF NOT EXISTS `business_applications` (
        `id` CHAR(26) NOT NULL,
        `business_name` VARCHAR(255) NOT NULL,
        `contact_name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) NULL DEFAULT NULL,
        `website` VARCHAR(255) NULL DEFAULT NULL,
        `message` TEXT NULL DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `admin_notes` TEXT NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL,
        `reviewed_at` DATETIME NULL DEFAULT NULL,
        `reviewed_by` CHAR(26) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_ba_status` (`status`),
        KEY `idx_ba_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
