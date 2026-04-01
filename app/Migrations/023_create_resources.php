<?php

declare(strict_types=1);

/**
 * Migration 023: Create resources table.
 *
 * Per roadmap Phase R — bookable resources for the resource pattern
 * (hotel rooms, meeting rooms, vacation rentals, equipment).
 */
return [
    "CREATE TABLE IF NOT EXISTS `resources` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT NULL DEFAULT NULL,
        `capacity` INT NOT NULL DEFAULT 1 COMMENT 'Max guests/occupants',
        `cover_image_path` VARCHAR(500) NULL DEFAULT NULL,
        `amenities` JSON NULL DEFAULT NULL COMMENT 'Free-form tags array',
        `price_per_night` DECIMAL(10,2) NULL DEFAULT NULL,
        `min_stay_nights` INT NOT NULL DEFAULT 1,
        `max_stay_nights` INT NOT NULL DEFAULT 30,
        `sort_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `resources_tenant_active_idx` (`tenant_id`, `is_active`, `sort_order`),
        CONSTRAINT `resources_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
