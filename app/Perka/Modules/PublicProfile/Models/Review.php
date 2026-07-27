<?php

declare(strict_types=1);

namespace App\Perka\Modules\PublicProfile\Models;

use App\Engine\Database;
use App\Engine\Ulid;

/**
 * Perka · PublicProfile · Review data model.
 *
 * Mirrors the sibling BusinessProfile model style: no ORM, raw PDO via the core
 * Database wrapper, ULID primary keys stored as CHAR(26), all methods return
 * arrays. Many rows per tenant in `perka_reviews`.
 *
 * The model persists raw column values only; validation (rating range, publish
 * flag coercion, date normalisation) lives in the admin controller/service,
 * keeping the model thin.
 */
final class Review
{
    /** Columns this model will accept on create/update (whitelist). */
    private const WRITABLE = [
        'rating', 'body', 'reviewer_name',
        'is_published', 'reviewed_at', 'sort_order',
    ];

    /**
     * Find a review by its own ID.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `perka_reviews` WHERE `id` = ? LIMIT 1',
            [$id]
        );

        return $rows[0] ?? null;
    }

    /**
     * All reviews for a tenant (any publish state), for the admin list.
     * Ordered the same way the public page orders published rows.
     *
     * @return list<array<string, mixed>>
     */
    public static function allForTenant(string $tenantId): array
    {
        return Database::query(
            'SELECT * FROM `perka_reviews`
             WHERE `tenant_id` = ?
             ORDER BY `sort_order` ASC, `reviewed_at` DESC, `created_at` DESC',
            [$tenantId]
        );
    }

    /**
     * Create a review row for a tenant.
     *
     * @param array<string, mixed> $data Writable columns (see WRITABLE).
     * @return string The new review ID (ULID)
     */
    public static function create(string $tenantId, array $data): string
    {
        $id = Ulid::generate();

        $columns = ['id', 'tenant_id'];
        $values  = [$id, $tenantId];

        foreach (self::WRITABLE as $field) {
            if (array_key_exists($field, $data)) {
                $columns[] = $field;
                $values[]  = $data[$field];
            }
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList   = implode('`, `', $columns);

        Database::execute(
            "INSERT INTO `perka_reviews` (`{$columnList}`) VALUES ({$placeholders})",
            $values
        );

        return $id;
    }

    /**
     * Update a review row by ID. Only whitelisted columns are applied.
     *
     * @param array<string, mixed> $data Key-value pairs to update.
     */
    public static function update(string $id, array $data): void
    {
        $sets   = [];
        $values = [];

        foreach (self::WRITABLE as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]   = "`{$field}` = ?";
                $values[] = $data[$field];
            }
        }

        if ($sets === []) {
            return;
        }

        $values[] = $id;

        Database::execute(
            'UPDATE `perka_reviews` SET ' . implode(', ', $sets) . ' WHERE `id` = ?',
            $values
        );
    }

    /**
     * Delete a review row by ID.
     */
    public static function delete(string $id): void
    {
        Database::execute('DELETE FROM `perka_reviews` WHERE `id` = ?', [$id]);
    }
}
