<?php

declare(strict_types=1);

namespace App\Models;

use App\Engine\Database;

/**
 * Booking data model.
 *
 * Provides query methods for the admin bookings list:
 * - cross-tenant views (operator)
 * - per-tenant views (business user + operator)
 * - status updates with validation
 *
 * All queries join tenant name for the "Tenant" column where needed.
 */
final class Booking
{
    /** Valid booking statuses */
    private const VALID_STATUSES = [
        'pending', 'confirmed', 'cancelled', 'completed', 'no_show', 'rescheduled',
    ];

    /**
     * Find a booking by ID.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        $rows = Database::query(
            "SELECT b.*, t.`name` AS `tenant_name`, t.`slug` AS `tenant_slug`,
                    c.`name` AS `customer_name`, c.`email` AS `customer_email`,
                    s.`name` AS `service_name`
             FROM `bookings` b
             LEFT JOIN `tenants` t ON t.`id` = b.`tenant_id`
             LEFT JOIN `customers` c ON c.`id` = b.`customer_id`
             LEFT JOIN `services` s ON s.`id` = b.`service_id`
             WHERE b.`id` = ?
             LIMIT 1",
            [$id]
        );

        return $rows[0] ?? null;
    }

    /**
     * Get all bookings cross-tenant (operator view).
     *
     * @return list<array<string, mixed>>
     */
    public static function all(
        ?string $status,
        ?string $from,
        ?string $to,
        ?string $search,
        int $limit,
        int $offset,
    ): array {
        [$where, $bindings] = self::buildFilters($status, $from, $to, $search);

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return Database::query(
            "SELECT b.*, t.`name` AS `tenant_name`, t.`slug` AS `tenant_slug`,
                    c.`name` AS `customer_name`, c.`email` AS `customer_email`,
                    s.`name` AS `service_name`
             FROM `bookings` b
             LEFT JOIN `tenants` t ON t.`id` = b.`tenant_id`
             LEFT JOIN `customers` c ON c.`id` = b.`customer_id`
             LEFT JOIN `services` s ON s.`id` = b.`service_id`
             {$whereClause}
             ORDER BY b.`start_datetime` DESC
             LIMIT ? OFFSET ?",
            array_merge($bindings, [$limit, $offset])
        );
    }

    /**
     * Count all bookings cross-tenant, with optional filters.
     */
    public static function countAll(
        ?string $status,
        ?string $from,
        ?string $to,
        ?string $search,
    ): int {
        [$where, $bindings] = self::buildFilters($status, $from, $to, $search);

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = Database::query(
            "SELECT COUNT(*) as cnt FROM `bookings` b {$whereClause}",
            $bindings
        );

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * Get bookings for a specific tenant.
     *
     * @return list<array<string, mixed>>
     */
    public static function forTenant(
        string $tenantId,
        ?string $status,
        ?string $from,
        ?string $to,
        int $limit,
        int $offset,
    ): array {
        [$where, $bindings] = self::buildFilters($status, $from, $to);
        $where[] = 'b.`tenant_id` = ?';
        $bindings[] = $tenantId;

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        return Database::query(
            "SELECT b.*, t.`name` AS `tenant_name`, t.`slug` AS `tenant_slug`,
                    c.`name` AS `customer_name`, c.`email` AS `customer_email`,
                    s.`name` AS `service_name`
             FROM `bookings` b
             LEFT JOIN `tenants` t ON t.`id` = b.`tenant_id`
             LEFT JOIN `customers` c ON c.`id` = b.`customer_id`
             LEFT JOIN `services` s ON s.`id` = b.`service_id`
             {$whereClause}
             ORDER BY b.`start_datetime` DESC
             LIMIT ? OFFSET ?",
            array_merge($bindings, [$limit, $offset])
        );
    }

    /**
     * Count bookings for a specific tenant.
     */
    public static function countForTenant(
        string $tenantId,
        ?string $status,
        ?string $from,
        ?string $to,
    ): int {
        [$where, $bindings] = self::buildFilters($status, $from, $to);
        $where[] = 'b.`tenant_id` = ?';
        $bindings[] = $tenantId;

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $rows = Database::query(
            "SELECT COUNT(*) as cnt FROM `bookings` b {$whereClause}",
            $bindings
        );

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * Update a booking's status.
     *
     * @return bool True if a row was updated.
     */
    public static function updateStatus(string $id, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return false;
        }

        $affected = Database::execute(
            "UPDATE `bookings` SET `status` = ?, `updated_at` = NOW() WHERE `id` = ?",
            [$status, $id]
        );

        return $affected > 0;
    }

    /**
     * Build filter clauses for booking queries.
     *
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private static function buildFilters(
        ?string $status = null,
        ?string $from = null,
        ?string $to = null,
        ?string $search = null,
    ): array {
        $where = [];
        $bindings = [];

        if ($status !== null && $status !== '') {
            $where[] = 'b.`status` = ?';
            $bindings[] = $status;
        }

        if ($from !== null && $from !== '') {
            $where[] = 'b.`start_datetime` >= ?';
            $bindings[] = $from . ' 00:00:00';
        }

        if ($to !== null && $to !== '') {
            $where[] = 'b.`start_datetime` <= ?';
            $bindings[] = $to . ' 23:59:59';
        }

        if ($search !== null && $search !== '') {
            // Search in customer name/email via a subquery join
            $where[] = '(b.`id` LIKE ? OR EXISTS (SELECT 1 FROM `customers` cs WHERE cs.`id` = b.`customer_id` AND (cs.`name` LIKE ? OR cs.`email` LIKE ?)))';
            $term = '%' . $search . '%';
            $bindings[] = $term;
            $bindings[] = $term;
            $bindings[] = $term;
        }

        return [$where, $bindings];
    }
}
