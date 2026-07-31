<?php

declare(strict_types=1);

/**
 * Perka · WhatsAppAutomation · Migration 001 — create perka_whatsapp_profiles.
 *
 * Per-tenant WhatsApp automation config consumed by an external n8n workflow
 * (via GET /api/whatsapp/profile) and an external AI agent. This table holds
 * ONLY automation data that has no home in the booking engine or on `tenants`:
 * the Evolution API instance name and a manually-entered free-text knowledge
 * base. It never duplicates tenant identity — `business_name` is read from
 * `tenants.name` via `tenant_id` at read time, never stored here.
 *
 * One profile per tenant (`tenant_id` UNIQUE) — the simplest correct default;
 * a real multi-instance need can relax it later with a new migration. The
 * `whatsapp_instance` name is globally UNIQUE (one Evolution instance maps to
 * exactly one tenant, so the n8n lookup is unambiguous).
 *
 * The foreign key is ON DELETE CASCADE — mandatory so this leftover perka_
 * table can never block core tenant deletion after app/Perka is removed.
 *
 * File format matches core migrations (returns an array of SQL statements) but
 * this file is run by the Perka-owned runner (App\Perka\Shared\PerkaMigrator),
 * NOT the core Migrator. It never touches settings.db_version.
 */
return [
    "CREATE TABLE IF NOT EXISTS `perka_whatsapp_profiles` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `whatsapp_instance` VARCHAR(255) NOT NULL COMMENT 'Evolution API WhatsApp instance name for this tenant',
        `business_knowledge` TEXT NULL DEFAULT NULL COMMENT 'Manually-entered free-text knowledge base for the external AI agent',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `perka_whatsapp_profiles_instance_unique` (`whatsapp_instance`),
        UNIQUE KEY `perka_whatsapp_profiles_tenant_unique` (`tenant_id`),
        CONSTRAINT `perka_whatsapp_profiles_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
