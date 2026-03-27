<?php

declare(strict_types=1);

/**
 * Migration 022: Add deletion request tracking to customers.
 *
 * Adds a proper deletion_requested_at column so the operator queue
 * can filter and display pending deletion requests instead of
 * relying on text markers in the notes field.
 */
return [
    "ALTER TABLE `customers` ADD COLUMN `deletion_requested_at` TIMESTAMP NULL DEFAULT NULL AFTER `anonymized_at`",
    "CREATE INDEX `customers_deletion_pending_idx` ON `customers` (`deletion_requested_at`, `is_anonymized`)",
];
