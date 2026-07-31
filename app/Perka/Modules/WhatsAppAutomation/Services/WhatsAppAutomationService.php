<?php

declare(strict_types=1);

namespace App\Perka\Modules\WhatsAppAutomation\Services;

use App\Engine\Database;
use App\Engine\TimeSlotCalculator;
use App\Perka\Modules\WhatsAppAutomation\Models\WhatsAppProfile;

/**
 * Perka · WhatsAppAutomation · service layer.
 *
 * Orchestrates the WhatsAppProfile model and owns the semantics the thin model
 * deliberately does not: the not-found/inactive collapse for the public n8n
 * endpoint, and `is_active` normalisation on write.
 *
 * Identity (`business_name`) is never stored on the profile — it is read from
 * `tenants.name` via the model's join at read time.
 *
 * Scope note (v1): `business_knowledge` is a plain, manually-entered free-text
 * field. This service intentionally does NOT auto-populate it from services,
 * opening hours, or team data — that is a separate future increment
 * ("Auto-sync business data").
 */
final class WhatsAppAutomationService
{
    /**
     * Resolve an active profile for the external n8n workflow, keyed by the
     * Evolution API instance name and composed with the tenant's name.
     *
     * Returns null when the instance is unknown OR the profile is inactive —
     * the two cases are intentionally indistinguishable so the endpoint can
     * answer both with the same 404 (no existence leak).
     *
     * @return array{business_name: string, business_knowledge: string|null, active: true}|null
     */
    public function getByInstance(string $instance): ?array
    {
        if ($instance === '') {
            return null;
        }

        $row = WhatsAppProfile::findByInstance($instance);

        if ($row === null || (int) $row['is_active'] !== 1) {
            return null;
        }

        $knowledge = $row['business_knowledge'];

        return [
            'business_name'      => (string) ($row['business_name'] ?? ''),
            'business_knowledge' => ($knowledge === null || $knowledge === '') ? null : (string) $knowledge,
            'active'             => true,
        ];
    }

    /**
     * Resolve bookable availability for a date, keyed by the Evolution API
     * instance name, for the external n8n workflow / AI agent.
     *
     * Chain: instance → active profile → active tenant → read-only slot
     * computation via the existing TimeSlotCalculator (the same engine the
     * public booking page uses). This NEVER touches BookingService or any
     * write path — it is a pure read.
     *
     * Returns null when the instance is unknown, the profile is inactive, or
     * the tenant is not active — the caller answers all three with the same
     * 404 (no existence leak), exactly as getByInstance() does.
     *
     * v1 supports the `timeslot` booking pattern. For an active tenant on any
     * other pattern (resource/capacity/event) `slots` is null (not []) so the
     * agent can distinguish "not wired into this endpoint yet" from "computed,
     * none free" — a clean extension point for those patterns later.
     *
     * When $serviceId is null the tenant's default slot_duration_minutes is
     * used (a neutral, general-availability answer). When provided, slots are
     * sized to that service's duration; the calculator itself validates the id
     * against the tenant and ignores an unknown one.
     *
     * @return array{
     *     business_name: string, date: string, timezone: string,
     *     booking_pattern: string, slot_duration_minutes: int,
     *     slots: list<string>|null
     * }|null
     */
    public function getAvailabilityByInstance(string $instance, string $date, ?string $serviceId = null): ?array
    {
        if ($instance === '') {
            return null;
        }

        $profile = WhatsAppProfile::findByInstance($instance);
        if ($profile === null || (int) $profile['is_active'] !== 1) {
            return null;
        }

        // Full tenant row, gated on active status (mirrors the booking engine's
        // own resolveTenant()). TimeSlotCalculator reads timezone, buffer,
        // slot_duration_minutes and the advance-window fields off this row.
        $tenantRows = Database::query(
            "SELECT * FROM `tenants` WHERE `id` = ? AND `status` = 'active' LIMIT 1",
            [$profile['tenant_id']]
        );
        $tenant = $tenantRows[0] ?? null;
        if ($tenant === null) {
            return null;
        }

        $pattern  = (string) ($tenant['booking_pattern'] ?? '');

        // Effective appointment length reported to the agent: the chosen
        // service's duration when a valid service_id is given, else the tenant
        // default. This mirrors TimeSlotCalculator's own resolution (service
        // must belong to the tenant and be active), so the reported value
        // always matches the duration the slots were actually fitted to.
        $duration = (int) ($tenant['slot_duration_minutes'] ?? 30);
        if ($serviceId !== null && $serviceId !== '') {
            $svc = Database::query(
                'SELECT `duration_minutes` FROM `services` WHERE `id` = ? AND `tenant_id` = ? AND `is_active` = 1 LIMIT 1',
                [$serviceId, $tenant['id']]
            );
            if (!empty($svc)) {
                $duration = (int) $svc[0]['duration_minutes'];
            }
        }

        $base = [
            'business_name'         => (string) ($tenant['name'] ?? ''),
            'date'                  => $date,
            'timezone'              => (string) ($tenant['timezone'] ?? 'UTC'),
            'booking_pattern'       => $pattern,
            'slot_duration_minutes' => $duration,
        ];

        // Only the timeslot pattern is computed by TimeSlotCalculator; other
        // active patterns return slots: null (a distinct "unsupported here yet"
        // signal), never an empty list.
        if ($pattern !== 'timeslot') {
            return $base + ['slots' => null];
        }

        $result = TimeSlotCalculator::getAvailableSlots($tenant, $date, $serviceId ?: null, null);

        // Flatten to bare start-time strings — the agent only needs "when",
        // and the duration is already conveyed once via slot_duration_minutes.
        $slots = [];
        foreach (($result['slots'] ?? []) as $slot) {
            if (isset($slot['time'])) {
                $slots[] = (string) $slot['time'];
            }
        }

        return $base + ['slots' => $slots];
    }

    /**
     * Get a tenant's profile for admin/editing, with is_active decoded to int.
     * Returns null if the tenant has no profile yet.
     *
     * @return array<string, mixed>|null
     */
    public function getForTenant(string $tenantId): ?array
    {
        $row = WhatsAppProfile::findByTenantId($tenantId);

        if ($row === null) {
            return null;
        }

        $row['is_active'] = (int) $row['is_active'];

        return $row;
    }

    /**
     * Create or update a tenant's profile from raw input.
     * Only known fields are persisted; is_active is normalised to 0/1.
     *
     * @param array<string, mixed> $input
     * @return string The profile ID (ULID)
     */
    public function save(string $tenantId, array $input): string
    {
        $data = $this->encodeWritable($input);

        $existing = WhatsAppProfile::findByTenantId($tenantId);

        if ($existing === null) {
            return WhatsAppProfile::create($tenantId, $data);
        }

        WhatsAppProfile::update($existing['id'], $data);

        return $existing['id'];
    }

    /**
     * Build the persistable data array from raw input: instance passed through
     * (empty string is not allowed by the caller), knowledge nulled when empty,
     * is_active coerced to a 0/1 flag.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function encodeWritable(array $input): array
    {
        $data = [];

        if (array_key_exists('whatsapp_instance', $input)) {
            $data['whatsapp_instance'] = (string) $input['whatsapp_instance'];
        }

        if (array_key_exists('business_knowledge', $input)) {
            $value = $input['business_knowledge'];
            $data['business_knowledge'] = ($value === '' || $value === null) ? null : (string) $value;
        }

        if (array_key_exists('is_active', $input)) {
            $data['is_active'] = ((int) $input['is_active'] === 1) ? 1 : 0;
        }

        return $data;
    }
}
