<?php

declare(strict_types=1);

/**
 * Migration 004: Create audit_log table.
 *
 * Structured audit logging per PRD §XIX and .ai/23-VoxelBooking-Legal-Logging.md §5.
 * Append-only, PII-redacted, retention-managed.
 *
 * Every meaningful mutation produces an entry:
 * - who acted (actor_type, actor_id)
 * - on what tenant (tenant_id, NULL for system events)
 * - on what entity (entity_type, entity_id)
 * - what happened (action)
 * - context (details JSON — PII redacted)
 * - when (created_at UTC)
 * - correlation (request_id, ip_address)
 */
return [
    "CREATE TABLE IF NOT EXISTS `audit_log` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `tenant_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `actor_type` VARCHAR(20) NOT NULL COMMENT 'Valid: operator, business_user, customer, system, api',
        `actor_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `action` VARCHAR(100) NOT NULL,
        `entity_type` VARCHAR(50) NOT NULL,
        `entity_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
        `details` JSON NULL DEFAULT NULL,
        `ip_address` VARCHAR(45) NULL DEFAULT NULL,
        `request_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `audit_log_tenant_created` (`tenant_id`, `created_at`),
        KEY `audit_log_actor` (`actor_type`, `actor_id`),
        KEY `audit_log_action` (`action`),
        KEY `audit_log_entity` (`entity_type`, `entity_id`),
        KEY `audit_log_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
