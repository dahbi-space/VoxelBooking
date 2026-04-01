<?php
/**
 * Tenant Settings — Privacy tab.
 *
 * Variables: $tenant, $tenantId, $csrfToken, $activeTab, $flash, $old
 */
$tenant   = $tenant ?? [];
$tenantId = $tenantId ?? '';

$checked = fn(string $field) => ((int) ($tenant[$field] ?? 0)) === 1 ? 'checked' : '';

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.tenant_settings.title') ?></h2>
        <p class="vb-page-subtitle"><?= e($tenant['name'] ?? '') ?></p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="vb-alert vb-alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'info' ? 'info' : 'error') ?>">
        <i data-lucide="<?= $flash['type'] === 'success' ? 'check' : ($flash['type'] === 'info' ? 'info' : 'alert-circle') ?>"></i>
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/_tabs.php'; ?>

<form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/settings/privacy" class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-form-grid">
                <div class="vb-settings-toggles">
                    <label class="vb-settings-toggle-item">
                        <input type="hidden" name="requires_consent" value="0">
                        <input type="checkbox" name="requires_consent" value="1" <?= $checked('requires_consent') ?>>
                        <div>
                            <div class="vb-settings-toggle-label"><?= __('admin.tenant_settings.field_requires_consent') ?></div>
                        </div>
                    </label>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-privacy-url"><?= __('admin.tenant_settings.field_privacy_url') ?></label>
                    <input type="url" class="vb-input" id="ts-privacy-url" name="privacy_policy_url"
                           value="<?= e($tenant['privacy_policy_url'] ?? '') ?>" maxlength="500"
                           placeholder="https://...">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-consent-text"><?= __('admin.tenant_settings.field_consent_text') ?></label>
                    <input type="text" class="vb-input" id="ts-consent-text" name="consent_text"
                           value="<?= e($tenant['consent_text'] ?? '') ?>" maxlength="500">
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_consent_hint') ?></span>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-retention"><?= __('admin.tenant_settings.field_data_retention') ?></label>
                    <input type="number" class="vb-input" id="ts-retention" name="data_retention_months"
                           value="<?= e((string) ($tenant['data_retention_months'] ?? '24')) ?>"
                           min="1" step="1" style="max-width: 100px;">
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_data_retention_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="vb-form-actions">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-privacy-btn">
            <i data-lucide="save" style="width: 15px; height: 15px;"></i>
            <?= __('admin.settings.save_button') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
