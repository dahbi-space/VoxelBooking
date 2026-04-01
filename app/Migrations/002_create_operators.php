<?php

declare(strict_types=1);

/**
 * Migration 002: Create operators table.
 *
 * Operators: the person who installed the application.
 * Global admin account, not tenant-scoped.
 */
return [
    "CREATE TABLE IF NOT EXISTS `operators` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `operators_email_unique` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `auth_emails` (
        `email` VARCHAR(255) NOT NULL,
        `user_type` VARCHAR(15) NOT NULL COMMENT 'operator or business_user',
        `user_id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        PRIMARY KEY (`email`),
        INDEX `auth_emails_user` (`user_type`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `login_tokens` (
        `id` CHAR(26) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `type` VARCHAR(10) NOT NULL COMMENT 'otp or magic_link',
        `token_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 of raw token/code',
        `remember_me` TINYINT(1) NOT NULL DEFAULT 0,
        `expires_at` DATETIME NOT NULL,
        `used_at` DATETIME NULL DEFAULT NULL,
        `ip_address` VARCHAR(45) NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `login_tokens_email_type` (`email`, `type`, `expires_at`),
        INDEX `login_tokens_hash` (`token_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
