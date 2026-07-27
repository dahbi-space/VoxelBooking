<?php

declare(strict_types=1);

/**
 * Perka · PublicProfile · Migration 002 — create perka_reviews.
 *
 * Read-only business testimonials shown on the public profile
 * (/business/{slug}) and curated in the Business Profile admin. Core
 * VoxelBooking has no reviews/ratings primitive of any kind, so this is a new
 * Perka-owned table — the sanctioned extension pattern (see PERKA_ARCHITECTURE:
 * "new features = new tables + new services + new controllers"). It never
 * touches a core table.
 *
 * Scope of a review:
 *   - `rating`        1–5 stars (validated in the admin controller/service).
 *   - `body`          the testimonial text.
 *   - `reviewer_name` optional; NULL renders as "Anonymous" on the page.
 *   - `is_published`  only published rows are ever read publicly (default 0).
 *   - `reviewed_at`   display date, decoupled from `created_at` so an operator
 *                     can backdate imported testimonials.
 *   - `sort_order`    manual ordering, mirroring staff/services.
 *
 * Reviews are tied to the BUSINESS (tenant), not to individual staff/services,
 * matching the "business reviews" brief. The foreign key is ON DELETE CASCADE —
 * mandatory so this leftover perka_ table can never block core tenant deletion
 * after app/Perka is removed.
 *
 * Run by the Perka-owned runner (App\Perka\Shared\PerkaMigrator), NOT the core
 * Migrator; it never touches settings.db_version.
 */
return [
    "CREATE TABLE IF NOT EXISTS `perka_reviews` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `rating` TINYINT UNSIGNED NOT NULL COMMENT '1-5 stars',
        `body` TEXT NOT NULL COMMENT 'Testimonial text',
        `reviewer_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'NULL renders as Anonymous',
        `is_published` TINYINT(1) NOT NULL DEFAULT 0,
        `reviewed_at` DATETIME NULL DEFAULT NULL COMMENT 'Display date (may be backdated for imports)',
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `perka_reviews_tenant_idx` (`tenant_id`),
        KEY `perka_reviews_published_idx` (`is_published`),
        KEY `perka_reviews_sort_idx` (`sort_order`),
        CONSTRAINT `perka_reviews_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
