<?php

declare(strict_types=1);

/**
 * Admin: edit a tenant's WhatsApp automation profile (body only).
 *
 * Rendered by PerkaView and injected as $content into the core admin.layout by
 * WhatsAppController::shell(). Do NOT include the layout here.
 *
 * Variables: $tenantId, $tenant, $profile (array|null), $csrfToken
 */

use App\Perka\Shared\PerkaView;

$tenantId  = $tenantId ?? '';
$tenant    = $tenant ?? [];
$profile   = $profile ?? null;
$csrfToken = $csrfToken ?? '';

$e = static fn (mixed $v): string => PerkaView::e($v);

// Active defaults to ON for a brand-new profile (matches the table default);
// for an existing row, honour the stored flag.
$isActive = $profile === null ? true : ((int) ($profile['is_active'] ?? 0) === 1);

// Value helper: prefer flashed old() input, then stored profile, then ''.
$val = static function (string $key, mixed $stored) {
    return old($key, $stored ?? '');
};

// Re-derive the active toggle from flashed input when a save round-tripped.
$activeChecked = old('is_active', $isActive ? '1' : '') !== '' ? true : ($profile === null ? true : $isActive);
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title">WhatsApp Automation</h2>
        <p class="vb-page-subtitle"><?= $e($tenant['name'] ?? '') ?></p>
    </div>
</div>

<form method="post" action="/admin/tenants/<?= $e($tenantId) ?>/whatsapp">
    <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken) ?>">

    <div class="vb-card">
        <div class="vb-card-body">
            <div class="vb-form-group">
                <label class="vb-label" for="wa-instance">WhatsApp instance name</label>
                <input class="vb-input" id="wa-instance" name="whatsapp_instance" type="text" maxlength="255"
                       required
                       placeholder="e.g. acme-salon"
                       value="<?= $e($val('whatsapp_instance', $profile['whatsapp_instance'] ?? '')) ?>">
                <span class="vb-settings-hint">The Evolution API instance name for this tenant. Must be unique.</span>
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="wa-knowledge">Business knowledge</label>
                <textarea class="vb-input vb-textarea" id="wa-knowledge" name="business_knowledge" rows="10"
                          placeholder="Free-text knowledge the AI agent uses to answer customer questions…"><?= $e($val('business_knowledge', $profile['business_knowledge'] ?? '')) ?></textarea>
                <span class="vb-settings-hint">Plain text, entered manually. Used by the external AI agent to answer customer questions on WhatsApp.</span>
            </div>

            <div class="vb-form-group">
                <label class="vb-label" style="display:flex;align-items:center;gap:.5rem;">
                    <input type="checkbox" name="is_active" value="1" <?= $activeChecked ? 'checked' : '' ?>>
                    Automation active
                </label>
                <span class="vb-settings-hint">When off, the API reports this instance as not found (the agent stops answering).</span>
            </div>
        </div>
    </div>

    <div class="vb-form-actions" style="margin-top:1rem;">
        <button type="submit" class="vb-btn vb-btn-primary">
            <i data-lucide="save"></i>
            Save
        </button>
    </div>
</form>
