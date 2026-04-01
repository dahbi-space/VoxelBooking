<?php

declare(strict_types=1);

/**
 * Migration 024: Create seasonal_pricing table.
 *
 * Per roadmap Phase R — date-range price overrides for resources.
 * A resource can have multiple non-overlapping seasonal pricing entries.
 */
return [
    "CREATE TABLE IF NOT EXISTS `seasonal_pricing` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `resource_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `price_per_night` DECIMAL(10,2) NOT NULL,
        `label` VARCHAR(255) NULL DEFAULT NULL COMMENT 'e.g. High Season, Holiday Rate',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `seasonal_pricing_resource_dates_idx` (`resource_id`, `start_date`, `end_date`),
        KEY `seasonal_pricing_tenant_idx` (`tenant_id`),
        CONSTRAINT `seasonal_pricing_resource_fk` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
        CONSTRAINT `seasonal_pricing_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
