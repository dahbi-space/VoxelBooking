<?php

declare(strict_types=1);

/**
 * Migration 025: Create capacity_slots table.
 *
 * capacity_slots define recurring weekly time windows for capacity-pattern tenants
 * (restaurants, escape rooms, group classes). Each slot specifies a day of week,
 * start/end time, maximum total capacity, minimum and maximum party size per
 * booking, and an optional label (e.g. "Early Dinner", "Late Seating").
 *
 * Bookings for capacity tenants use party_size to track seats consumed per booking.
 * CapacityCalculator sums confirmed/rescheduled party_size values against max_capacity
 * to determine remaining spots.
 */
return [
    "CREATE TABLE IF NOT EXISTS `capacity_slots` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '0=Monday, 6=Sunday',
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        `max_capacity` INT UNSIGNED NOT NULL DEFAULT 20,
        `min_party_size` INT UNSIGNED NOT NULL DEFAULT 1,
        `max_party_size` INT UNSIGNED NOT NULL DEFAULT 8,
        `label` VARCHAR(100) NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_capacity_slots_tenant` (`tenant_id`),
        INDEX `idx_capacity_slots_day` (`tenant_id`, `day_of_week`, `is_active`),
        CONSTRAINT `fk_capacity_slots_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
