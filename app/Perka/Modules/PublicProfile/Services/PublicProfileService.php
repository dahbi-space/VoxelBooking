<?php

declare(strict_types=1);

namespace App\Perka\Modules\PublicProfile\Services;

use App\Models\Tenant;
use App\Perka\Modules\PublicProfile\Models\BusinessProfile;

/**
 * Perka · PublicProfile · service layer.
 *
 * Orchestrates the BusinessProfile model and composes it with the core Tenant
 * record. Owns the two things the thin model deliberately does not:
 *   1. JSON (de)serialisation of the `gallery` and `socials` columns.
 *   2. Publish semantics (`is_published` + first-publish `published_at`).
 *
 * Identity fields (name, slug, cover_image_path, timezone) are never stored on
 * the profile — they are read from the tenant here at read time.
 */
final class PublicProfileService
{
    /** Input keys that map to JSON columns. */
    private const JSON_FIELDS = ['gallery', 'socials'];

    /** Scalar input keys the service will persist. */
    private const SCALAR_FIELDS = ['headline', 'about', 'seo_title', 'seo_description', 'theme'];

    /**
     * Get a tenant's profile for admin/editing, with JSON columns decoded.
     * Returns null if the tenant has no profile yet.
     *
     * @return array<string, mixed>|null
     */
    public function getForTenant(string $tenantId): ?array
    {
        $row = BusinessProfile::findByTenantId($tenantId);

        return $row === null ? null : $this->decode($row);
    }

    /**
     * Get a PUBLISHED profile by the tenant's public slug, composed with the
     * tenant identity data the profile intentionally does not duplicate.
     *
     * Returns null when the slug is unknown, has no profile, or is unpublished.
     *
     * @return array{tenant: array<string,mixed>, profile: array<string,mixed>}|null
     */
    public function getPublishedBySlug(string $slug): ?array
    {
        $tenant = Tenant::findBySlug($slug);
        if ($tenant === null) {
            return null;
        }

        $row = BusinessProfile::findByTenantId($tenant['id']);
        if ($row === null || (int) $row['is_published'] !== 1) {
            return null;
        }

        return [
            'tenant'  => $tenant,
            'profile' => $this->decode($row),
        ];
    }

    /**
     * Create or update a tenant's profile from raw input.
     * Only known fields are persisted; JSON fields are encoded here.
     *
     * @param array<string, mixed> $input
     * @return string The profile ID (ULID)
     */
    public function save(string $tenantId, array $input): string
    {
        $data = $this->encodeWritable($input);

        $existing = BusinessProfile::findByTenantId($tenantId);

        if ($existing === null) {
            return BusinessProfile::create($tenantId, $data);
        }

        BusinessProfile::update($existing['id'], $data);

        return $existing['id'];
    }

    /**
     * Publish a tenant's profile. Creates an empty profile first if none
     * exists. `published_at` is stamped only on the first publish.
     */
    public function publish(string $tenantId): void
    {
        $existing = BusinessProfile::findByTenantId($tenantId);

        if ($existing === null) {
            BusinessProfile::create($tenantId, [
                'is_published' => 1,
                'published_at' => $this->now(),
            ]);
            return;
        }

        $data = ['is_published' => 1];
        if (empty($existing['published_at'])) {
            $data['published_at'] = $this->now();
        }

        BusinessProfile::update($existing['id'], $data);
    }

    /**
     * Unpublish a tenant's profile (no-op if none exists). `published_at` is
     * left intact as the historical first-publish timestamp.
     */
    public function unpublish(string $tenantId): void
    {
        $existing = BusinessProfile::findByTenantId($tenantId);

        if ($existing === null) {
            return;
        }

        BusinessProfile::update($existing['id'], ['is_published' => 0]);
    }

    /**
     * Build the persistable data array from raw input: whitelisted scalars
     * passed through, JSON fields encoded (or set null when empty).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function encodeWritable(array $input): array
    {
        $data = [];

        foreach (self::SCALAR_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                $data[$field] = ($value === '' ? null : $value);
            }
        }

        foreach (self::JSON_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                $data[$field] = (empty($value) ? null : json_encode($value));
            }
        }

        if (array_key_exists('is_published', $input)) {
            $data['is_published'] = ((int) $input['is_published'] === 1) ? 1 : 0;
        }

        return $data;
    }

    /**
     * Decode a raw DB row: JSON columns to arrays, is_published to int.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        foreach (self::JSON_FIELDS as $field) {
            $row[$field] = ($row[$field] === null || $row[$field] === '')
                ? null
                : json_decode((string) $row[$field], true);
        }

        $row['is_published'] = (int) $row['is_published'];

        return $row;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
