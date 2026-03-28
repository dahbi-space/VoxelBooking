<?php

declare(strict_types=1);

/**
 * Migration 011: Create business_users table.
 *
 * Tenant-scoped users with owner/manager roles.
 * Runs after 003_create_tenants.php to support the tenant FK.
 *
 * Per PRD §XV (Phase 2 — Business Users & Admin Polish):
 * Business users are invited by the operator, scoped to a single tenant,
 * and restricted to their tenant's data by AuthMiddleware.
 */
return [
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
        KEY `business_users_active_idx` (`tenant_id`, `is_active`),
        CONSTRAINT `business_users_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
