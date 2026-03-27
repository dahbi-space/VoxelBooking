<?php

declare(strict_types=1);

/**
 * Migration 009: Create blocked_dates table.
 *
 * Per PRD §IV — temporary closures and holidays.
 * Can be tenant-level (staff_id and resource_id both NULL)
 * or entity-specific (staff or resource).
 */
return [
    "CREATE TABLE IF NOT EXISTS `blocked_dates` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `staff_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `resource_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `reason` VARCHAR(255) NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `blocked_dates_tenant_idx` (`tenant_id`, `start_date`, `end_date`),
        KEY `blocked_dates_staff_idx` (`staff_id`, `start_date`, `end_date`),
        CONSTRAINT `blocked_dates_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
        CONSTRAINT `blocked_dates_staff_fk` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
