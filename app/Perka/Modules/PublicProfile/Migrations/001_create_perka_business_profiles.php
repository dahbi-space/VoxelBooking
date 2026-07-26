<?php

declare(strict_types=1);

/**
 * Perka · PublicProfile · Migration 001 — create perka_business_profiles.
 *
 * A public-facing business profile served at /business/{slug}, where {slug} is
 * the EXISTING `tenants.slug`. This table holds ONLY marketing/public data that
 * has no home in the booking engine. It never duplicates tenant identity:
 * name, slug, cover_image_path and timezone are read from `tenants` via
 * `tenant_id` at render time.
 *
 * `about` is present because `tenants` has no `description`/`bio` column
 * (its `booking_page_description` is booking-page copy, a different surface).
 *
 * One profile per tenant (`tenant_id` UNIQUE). The foreign key is
 * ON DELETE CASCADE — mandatory so this leftover perka_ table can never block
 * core tenant deletion after app/Perka is removed.
 *
 * File format matches core migrations (returns an array of SQL statements) but
 * this file is run by the Perka-owned runner (App\Perka\Shared\PerkaMigrator),
 * NOT the core Migrator. It never touches settings.db_version.
 */
return [
    "CREATE TABLE IF NOT EXISTS `perka_business_profiles` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `headline` VARCHAR(255) NULL DEFAULT NULL,
        `about` TEXT NULL DEFAULT NULL COMMENT 'Public marketing copy (tenants has no description/bio)',
        `gallery` JSON NULL DEFAULT NULL COMMENT 'Extra image paths beyond tenants.cover_image_path',
        `socials` JSON NULL DEFAULT NULL COMMENT 'e.g. {\"instagram\":\"...\",\"facebook\":\"...\"}',
        `seo_title` VARCHAR(255) NULL DEFAULT NULL,
        `seo_description` VARCHAR(255) NULL DEFAULT NULL,
        `theme` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Named presentation theme key',
        `is_published` TINYINT(1) NOT NULL DEFAULT 0,
        `published_at` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `perka_business_profiles_tenant_unique` (`tenant_id`),
        KEY `perka_business_profiles_published_idx` (`is_published`),
        CONSTRAINT `perka_business_profiles_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
