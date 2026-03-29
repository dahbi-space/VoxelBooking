<?php

declare(strict_types=1);

/**
 * Migration 010: Create api_keys table.
 *
 * Per PRD §XII and .ai/22-VoxelBooking-Agent-API-Checklist.md:
 * Bearer token authentication for the Agent API.
 *
 * Keys are hashed (SHA-256) and never stored in plaintext.
 * The raw key is shown only once at creation time.
 * Scopes are stored as JSON array of scope strings.
 */
return [
    "CREATE TABLE IF NOT EXISTS `api_keys` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'SHA-256 of bearer token',
        `key_prefix` CHAR(8) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'First 8 chars of key for identification',
        `scopes` JSON NOT NULL COMMENT 'Array of scope strings',
        `role` VARCHAR(10) NOT NULL DEFAULT 'viewer' COMMENT 'Valid: agent, viewer',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `last_used_at` DATETIME NULL DEFAULT NULL,
        `expires_at` DATETIME NULL DEFAULT NULL,
        `created_by` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL COMMENT 'Operator ULID',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `api_keys_hash_unique` (`key_hash`),
        KEY `api_keys_prefix_idx` (`key_prefix`),
        KEY `api_keys_active_idx` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
