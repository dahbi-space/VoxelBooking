<?php

declare(strict_types=1);

namespace App\Models;

use App\Engine\Database;
use App\Engine\Ulid;

/**
 * Tenant data model.
 *
 * No ORM — raw PDO queries against the `tenants` table.
 * All methods return arrays (not objects) for consistency
 * with the rest of the codebase.
 */
final class Tenant
{
    /**
     * Get all tenants, optionally including archived.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM `tenants`';
        if (!$includeArchived) {
            $sql .= " WHERE `status` != 'archived'";
        }
        $sql .= ' ORDER BY `name` ASC';

        return Database::query($sql);
    }

    /**
     * Get tenants with optional search and status filters.
     *
     * @return list<array<string, mixed>>
     */
    public static function filtered(?string $search = null, ?string $status = null): array
    {
        $clauses = [];
        $params  = [];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $allowed = ['active', 'paused', 'archived'];
            if (in_array($status, $allowed, true)) {
                $clauses[] = '`status` = ?';
                $params[]  = $status;
            }
        }

        if ($search !== null && $search !== '') {
            $clauses[] = '(`name` LIKE ? OR `slug` LIKE ? OR `email` LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT * FROM `tenants`';
        if ($clauses) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $sql .= ' ORDER BY `name` ASC';

        return Database::query($sql, $params);
    }

    /**
     * Find a tenant by ID.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$id]
        );

        return $rows[0] ?? null;
    }

    /**
     * Find a tenant by slug.
     *
     * @return array<string, mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `tenants` WHERE `slug` = ? LIMIT 1',
            [$slug]
        );

        return $rows[0] ?? null;
    }

    /**
     * Create a new tenant.
     *
     * @param array<string, mixed> $data
     * @return string The new tenant ID (ULID)
     */
    public static function create(array $data): string
    {
        $id = Ulid::generate();

        $columns = ['id', 'slug', 'name', 'email', 'booking_pattern'];
        $values = [$id, $data['slug'], $data['name'], $data['email'], $data['booking_pattern']];

        // Optional fields
        $optionalFields = [
            'phone', 'timezone', 'locale', 'currency', 'brand_color',
            'notification_email', 'privacy_policy_url', 'consent_text',
        ];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $columns[] = $field;
                $values[] = $data[$field];
            }
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode('`, `', $columns);

        Database::execute(
            "INSERT INTO `tenants` (`{$columnList}`) VALUES ({$placeholders})",
            $values
        );

        return $id;
    }

    /**
     * Update a tenant by ID.
     *
     * @param array<string, mixed> $data Key-value pairs to update.
     */
    public static function update(string $id, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $sets = [];
        $values = [];
        foreach ($data as $key => $value) {
            $sets[] = "`{$key}` = ?";
            $values[] = $value;
        }
        $values[] = $id;

        Database::execute(
            'UPDATE `tenants` SET ' . implode(', ', $sets) . ' WHERE `id` = ?',
            $values
        );
    }

    /**
     * Check if a slug already exists.
     */
    public static function slugExists(string $slug, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM `tenants` WHERE `slug` = ?';
        $params = [$slug];

        if ($excludeId !== null) {
            $sql .= ' AND `id` != ?';
            $params[] = $excludeId;
        }

        $rows = Database::query($sql, $params);
        return (int) ($rows[0]['cnt'] ?? 0) > 0;
    }

    /**
     * Get tenant counts by status.
     *
     * @return array{active: int, paused: int, archived: int, total: int}
     */
    public static function counts(): array
    {
        $rows = Database::query(
            "SELECT `status`, COUNT(*) as cnt FROM `tenants` GROUP BY `status`"
        );

        $counts = ['active' => 0, 'paused' => 0, 'archived' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
            $counts['total'] += (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Get booking count for a tenant.
     */
    public static function bookingCount(string $tenantId): int
    {
        $rows = Database::query(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE `tenant_id` = ?",
            [$tenantId]
        );

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * Get active service count for a tenant.
     */
    public static function serviceCount(string $tenantId): int
    {
        $rows = Database::query(
            "SELECT COUNT(*) as cnt FROM `services` WHERE `tenant_id` = ? AND `is_active` = 1",
            [$tenantId]
        );

        return (int) ($rows[0]['cnt'] ?? 0);
    }
}
