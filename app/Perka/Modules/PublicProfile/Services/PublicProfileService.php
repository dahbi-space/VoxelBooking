<?php

declare(strict_types=1);

namespace App\Perka\Modules\PublicProfile\Services;

use App\Engine\Database;
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
            'tenant'    => $tenant,
            'profile'   => $this->decode($row),
            'services'  => $this->getActiveServices($tenant['id']),
            'hours'     => $this->getOpeningHours($tenant['id']),
            'team'      => $this->getTeam($tenant['id']),
            'reviews'   => $this->getPublishedReviews($tenant['id']),
            'aggregate' => $this->getReviewAggregate($tenant['id']),
        ];
    }

    /**
     * Read a tenant's most recent PUBLISHED reviews for read-only public display.
     *
     * Pure read over the Perka-owned `perka_reviews` table — no booking or
     * availability logic. Ordered by manual `sort_order`, then most recent
     * `reviewed_at` (falling back to `created_at`). Capped at $limit for the
     * profile section; the (future) dedicated /reviews page can pass a higher
     * cap or 0 for all. Private data is never selected — there is none.
     *
     * @return list<array<string, mixed>>
     */
    public function getPublishedReviews(string $tenantId, int $limit = 4): array
    {
        $sql = 'SELECT `id`, `rating`, `body`, `reviewer_name`, `reviewed_at`, `created_at`
                FROM `perka_reviews`
                WHERE `tenant_id` = ? AND `is_published` = 1
                ORDER BY `sort_order` ASC, `reviewed_at` DESC, `created_at` DESC';

        // A non-positive limit means "all" (used by the dedicated page later).
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        return Database::query($sql, [$tenantId]);
    }

    /**
     * Aggregate rating for a tenant's PUBLISHED reviews.
     *
     * Pure read over `perka_reviews`. Returns the published review count and the
     * average rating rounded to one decimal. When there are no published
     * reviews, `count` is 0 and `average` is null — the view hides the whole
     * section on that signal.
     *
     * @return array{count: int, average: float|null}
     */
    public function getReviewAggregate(string $tenantId): array
    {
        $rows = Database::query(
            'SELECT COUNT(*) AS cnt, AVG(`rating`) AS avg_rating
             FROM `perka_reviews`
             WHERE `tenant_id` = ? AND `is_published` = 1',
            [$tenantId]
        );

        $count = (int) ($rows[0]['cnt'] ?? 0);
        $avg   = $rows[0]['avg_rating'] ?? null;

        return [
            'count'   => $count,
            'average' => ($count > 0 && $avg !== null) ? round((float) $avg, 1) : null,
        ];
    }

    /**
     * Read a tenant's active staff for read-only public display ("Team"),
     * each enriched with their public bio and the services they can perform.
     *
     * Pure reads over the existing core `staff` and `service_staff` tables. The
     * roster mirrors the core public staff endpoint (name, title, avatar_path),
     * plus `bio` (shown in the detail modal) and a `services` list per member
     * (name, duration, price via the service_staff junction). Private columns
     * email/phone are never selected. No booking or availability logic runs.
     *
     * @return list<array<string, mixed>> Each member includes a `services` list.
     */
    public function getTeam(string $tenantId): array
    {
        $members = Database::query(
            'SELECT `id`, `name`, `title`, `avatar_path`, `bio`
             FROM `staff`
             WHERE `tenant_id` = ? AND `is_active` = 1
             ORDER BY `sort_order` ASC, `name` ASC',
            [$tenantId]
        );

        if ($members === []) {
            return [];
        }

        // Services each active staff member can perform, via the service_staff
        // junction — one grouped read (pure; no booking/availability logic).
        $rows = Database::query(
            'SELECT ss.`staff_id`, s.`id`, s.`name`, s.`duration_minutes`, s.`price`, s.`price_label`
             FROM `services` s
             INNER JOIN `service_staff` ss ON ss.`service_id` = s.`id`
             WHERE s.`tenant_id` = ? AND s.`is_active` = 1
             ORDER BY s.`sort_order` ASC, s.`name` ASC',
            [$tenantId]
        );

        $byStaff = [];
        foreach ($rows as $r) {
            $sid = (string) $r['staff_id'];
            unset($r['staff_id']);
            $byStaff[$sid][] = $r;
        }

        foreach ($members as &$m) {
            $m['services'] = $byStaff[(string) $m['id']] ?? [];
        }
        unset($m);

        return $members;
    }

    /**
     * Read a tenant's weekly opening hours for read-only public display.
     *
     * Pure read over the existing core `availability` table — the tenant-level
     * default rows (staff_id IS NULL, is_available = 1). This is the exact query
     * the core availability admin screen uses (AvailabilityController::index);
     * no availability/slot calculation is invoked. Rows are grouped by weekday.
     *
     * day_of_week is 0=Mon..6=Sun (ISO). Multiple rows per day are split shifts.
     * A day with no rows is closed (absent from the returned map). Tenants with
     * no such rows (e.g. non-timeslot patterns) yield an empty array, and the
     * section is hidden by the view.
     *
     * @return array<int, list<array{start_time: string, end_time: string}>>
     *         Map of day_of_week (0-6) => ordered list of {start_time, end_time}.
     */
    public function getOpeningHours(string $tenantId): array
    {
        $rows = Database::query(
            'SELECT `day_of_week`, `start_time`, `end_time`
             FROM `availability`
             WHERE `tenant_id` = ? AND `staff_id` IS NULL AND `is_available` = 1
             ORDER BY `day_of_week` ASC, `start_time` ASC',
            [$tenantId]
        );

        $byDay = [];
        foreach ($rows as $row) {
            $day = (int) $row['day_of_week'];
            $byDay[$day][] = [
                'start_time' => (string) $row['start_time'],
                'end_time'   => (string) $row['end_time'],
            ];
        }

        return $byDay;
    }

    /**
     * Read a tenant's active, bookable services for read-only public display.
     *
     * Pure read over the existing core `services` table — no booking or
     * availability logic is invoked. Mirrors the field selection and ordering
     * of the core public services endpoint (BookingApiController::services).
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveServices(string $tenantId): array
    {
        return Database::query(
            'SELECT `id`, `name`, `duration_minutes`, `price`, `price_label`
             FROM `services`
             WHERE `tenant_id` = ? AND `is_active` = 1
             ORDER BY `sort_order` ASC, `name` ASC',
            [$tenantId]
        );
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
