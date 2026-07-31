<?php

declare(strict_types=1);

namespace App\Perka\Modules\WhatsAppAutomation\Services;

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
