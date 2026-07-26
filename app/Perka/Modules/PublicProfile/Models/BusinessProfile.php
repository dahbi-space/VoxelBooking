<?php

declare(strict_types=1);

namespace App\Perka\Modules\PublicProfile\Models;

use App\Engine\Database;
use App\Engine\Ulid;

/**
 * Perka · PublicProfile · BusinessProfile data model.
 *
 * Mirrors the core model style (e.g. App\Models\Tenant): no ORM, raw PDO via
 * the core Database wrapper, ULID primary keys stored as CHAR(26), all methods
 * return arrays. One row per tenant in `perka_business_profiles`.
 *
 * This model persists raw column values only. JSON encoding/decoding of the
 * `gallery` and `socials` columns and all publish semantics live in
 * PublicProfileService, keeping the model thin.
 */
final class BusinessProfile
{
    /** Columns this model will accept on create/update (whitelist). */
    private const WRITABLE = [
        'headline', 'about', 'gallery', 'socials',
        'seo_title', 'seo_description', 'theme',
        'is_published', 'published_at',
    ];

    /**
     * Find a profile by its own ID.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `perka_business_profiles` WHERE `id` = ? LIMIT 1',
            [$id]
        );

        return $rows[0] ?? null;
    }

    /**
     * Find the profile belonging to a tenant.
     *
     * @return array<string, mixed>|null
     */
    public static function findByTenantId(string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `perka_business_profiles` WHERE `tenant_id` = ? LIMIT 1',
            [$tenantId]
        );

        return $rows[0] ?? null;
    }

    /**
     * Find a profile by the tenant's public slug (the /business/{slug} slug).
     * The slug lives on `tenants`, never duplicated here.
     *
     * @return array<string, mixed>|null
     */
    public static function findByTenantSlug(string $slug): ?array
    {
        $rows = Database::query(
            'SELECT `p`.* FROM `perka_business_profiles` `p`
             INNER JOIN `tenants` `t` ON `t`.`id` = `p`.`tenant_id`
             WHERE `t`.`slug` = ? LIMIT 1',
            [$slug]
        );

        return $rows[0] ?? null;
    }

    /**
     * Whether a tenant already has a profile row.
     */
    public static function existsForTenant(string $tenantId): bool
    {
        $rows = Database::query(
            'SELECT COUNT(*) AS cnt FROM `perka_business_profiles` WHERE `tenant_id` = ?',
            [$tenantId]
        );

        return (int) ($rows[0]['cnt'] ?? 0) > 0;
    }

    /**
     * Create a profile row for a tenant.
     *
     * @param array<string, mixed> $data Writable columns (see WRITABLE).
     * @return string The new profile ID (ULID)
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
            "INSERT INTO `perka_business_profiles` (`{$columnList}`) VALUES ({$placeholders})",
            $values
        );

        return $id;
    }

    /**
     * Update a profile row by ID. Only whitelisted columns are applied.
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
            'UPDATE `perka_business_profiles` SET ' . implode(', ', $sets) . ' WHERE `id` = ?',
            $values
        );
    }
}
