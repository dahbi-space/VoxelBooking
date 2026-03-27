<?php

declare(strict_types=1);

/**
 * Migration 008: Create availability table.
 *
 * Per PRD §IV — weekly schedule rules.
 * Each row defines a time window on a day of the week.
 * staff_id NULL = tenant-level default (applies to all staff without overrides).
 * day_of_week: 0=Mon, 6=Sun (ISO-8601 convention).
 */
return [
    "CREATE TABLE IF NOT EXISTS `availability` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `staff_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `day_of_week` TINYINT NOT NULL,
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        `is_available` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `availability_tenant_day_idx` (`tenant_id`, `day_of_week`),
        KEY `availability_staff_day_idx` (`staff_id`, `day_of_week`),
        CONSTRAINT `availability_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
        CONSTRAINT `availability_staff_fk` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
