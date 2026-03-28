<?php

declare(strict_types=1);

/**
 * Migration 002: Create auth user tables.
 *
 * Operators: the person who installed VoxelBooking.
 * Business users: tenant-scoped users with owner/manager roles.
 *
 * Note: business_users references tenants(id) which is created in 003.
 * MySQL defers FK checks within a transaction, and the migrator
 * processes files sequentially, so this is safe as long as tenants
 * exists before any INSERT occurs on business_users.
 */
return [
    "CREATE TABLE IF NOT EXISTS `operators` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `operators_email_unique` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `business_users` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `role` ENUM('owner','manager') NOT NULL DEFAULT 'manager',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `force_password_change` TINYINT(1) NOT NULL DEFAULT 0,
        `last_login_at` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `business_users_email_unique` (`email`),
        KEY `business_users_tenant_idx` (`tenant_id`),
        KEY `business_users_active_idx` (`tenant_id`, `is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

