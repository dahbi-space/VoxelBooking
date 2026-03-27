<?php

declare(strict_types=1);

/**
 * Migration 005: Create services table.
 *
 * Per PRD §IV — timeslot pattern services.
 * Each service represents a bookable offering (e.g., Haircut, Color & Highlights).
 * Services are per-tenant and drive the timeslot booking flow.
 */
return [
    "CREATE TABLE IF NOT EXISTS `services` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT NULL DEFAULT NULL,
        `duration_minutes` INT NOT NULL DEFAULT 30,
        `price` DECIMAL(10,2) NULL DEFAULT NULL,
        `price_label` VARCHAR(100) NULL DEFAULT NULL,
        `category` VARCHAR(100) NULL DEFAULT NULL,
        `color` VARCHAR(7) NULL DEFAULT NULL,
        `max_per_day` INT NULL DEFAULT NULL,
        `requires_staff` TINYINT(1) NOT NULL DEFAULT 1,
        `is_virtual` TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `services_tenant_active_idx` (`tenant_id`, `is_active`, `sort_order`),
        CONSTRAINT `services_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
