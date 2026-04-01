<?php
/**
 * Tenant Settings — Branding tab.
 *
 * Variables: $tenant, $tenantId, $csrfToken, $activeTab, $flash, $old
 */
$tenant   = $tenant ?? [];
$tenantId = $tenantId ?? '';

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

<form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/settings/branding" class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-form-grid vb-form-grid-2">
                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-brand-color"><?= __('admin.tenant_settings.field_brand_color') ?></label>
                    <div class="vb-settings-color-pair">
                        <input type="color" id="ts-brand-color" name="brand_color"
                               class="vb-settings-color-swatch"
                               value="<?= e($tenant['brand_color'] ?? '#2563EB') ?>">
                        <input type="text" class="vb-input vb-settings-color-hex" id="ts-brand-color-hex"
                               value="<?= e($tenant['brand_color'] ?? '#2563EB') ?>"
                               pattern="^#[0-9a-fA-F]{6}$" maxlength="7"
                               oninput="document.getElementById('ts-brand-color').value = this.value">
                    </div>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-brand-text"><?= __('admin.tenant_settings.field_brand_color_text') ?></label>
                    <div class="vb-settings-color-pair">
                        <input type="color" id="ts-brand-text" name="brand_color_text"
                               class="vb-settings-color-swatch"
                               value="<?= e($tenant['brand_color_text'] ?? '#FFFFFF') ?>">
                        <input type="text" class="vb-input vb-settings-color-hex" id="ts-brand-text-hex"
                               value="<?= e($tenant['brand_color_text'] ?? '#FFFFFF') ?>"
                               pattern="^#[0-9a-fA-F]{6}$" maxlength="7"
                               oninput="document.getElementById('ts-brand-text').value = this.value">
                    </div>
                </div>

                <div class="vb-settings-field" style="grid-column: 1 / -1;">
                    <label class="vb-label" for="ts-heading"><?= __('admin.tenant_settings.field_heading') ?></label>
                    <input type="text" class="vb-input" id="ts-heading" name="booking_page_heading"
                           value="<?= e($tenant['booking_page_heading'] ?? '') ?>" maxlength="255">
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_heading_hint') ?></span>
                </div>

                <div class="vb-settings-field" style="grid-column: 1 / -1;">
                    <label class="vb-label" for="ts-desc"><?= __('admin.tenant_settings.field_description') ?></label>
                    <textarea class="vb-input" id="ts-desc" name="booking_page_description"
                              rows="3" style="resize: vertical; height: auto; padding: 0.625rem 0.75rem;"><?= e($tenant['booking_page_description'] ?? '') ?></textarea>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_description_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="vb-form-actions">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-branding-btn">
            <i data-lucide="save" style="width: 15px; height: 15px;"></i>
            <?= __('admin.settings.save_button') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
