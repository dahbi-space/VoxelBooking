<?php
/**
 * Tenant Settings — General tab.
 *
 * Variables: $tenant, $tenantId, $csrfToken, $activeTab, $flash, $old
 */
$tenant   = $tenant ?? [];
$tenantId = $tenantId ?? '';
$old      = $old ?? null;

$val = function (string $field, string $default = '') use ($tenant, $old): string {
    if ($old !== null && array_key_exists($field, $old)) {
        return htmlspecialchars((string) $old[$field], ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string) ($tenant[$field] ?? $default), ENT_QUOTES, 'UTF-8');
};

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

<form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/settings" class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-form-grid vb-form-grid-2">
                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-name"><?= __('admin.tenant_settings.field_name') ?> <span style="color: var(--vb-error);">*</span></label>
                    <input type="text" class="vb-input" id="ts-name" name="name"
                           value="<?= $val('name') ?>" required maxlength="255">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-email"><?= __('admin.tenant_settings.field_email') ?> <span style="color: var(--vb-error);">*</span></label>
                    <input type="email" class="vb-input" id="ts-email" name="email"
                           value="<?= $val('email') ?>" required>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-slug"><?= __('admin.tenant_settings.field_slug') ?></label>
                    <input type="text" class="vb-input" id="ts-slug"
                           value="<?= e($tenant['slug'] ?? '') ?>" readonly>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_slug_hint') ?></span>
                    <?php $bookingUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/book/' . ($tenant['slug'] ?? ''); ?>
                    <div class="vb-settings-booking-url">
                        <code class="vb-settings-url-text"><?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?></code>
                        <button type="button"
                                class="vb-copy-btn"
                                data-copy-url="<?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?>"
                                @click="copyBookingUrl"
                                title="<?= __('admin.common.copy_booking_url') ?>">
                            <span class="vb-copy-icon"><i data-lucide="copy"></i></span>
                            <span class="vb-copy-check"><i data-lucide="check"></i></span>
                            <?= __('admin.common.copy_booking_url') ?>
                        </button>
                    </div>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-phone"><?= __('admin.tenant_settings.field_phone') ?></label>
                    <input type="tel" class="vb-input" id="ts-phone" name="phone"
                           value="<?= $val('phone') ?>">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-timezone"><?= __('admin.tenant_settings.field_timezone') ?></label>
                    <input type="text" class="vb-input" id="ts-timezone" name="timezone"
                           value="<?= $val('timezone', 'UTC') ?>" maxlength="100">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-locale"><?= __('admin.tenant_settings.field_locale') ?></label>
                    <input type="text" class="vb-input" id="ts-locale" name="locale"
                           value="<?= $val('locale', 'en') ?>" maxlength="10">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-currency"><?= __('admin.tenant_settings.field_currency') ?></label>
                    <input type="text" class="vb-input" id="ts-currency" name="currency"
                           value="<?= $val('currency', 'EUR') ?>" maxlength="3" style="max-width: 80px;">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-pattern"><?= __('admin.tenant_settings.field_pattern') ?></label>
                    <input type="text" class="vb-input" id="ts-pattern"
                           value="<?= e($tenant['booking_pattern'] ?? '') ?>" readonly>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_pattern_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="vb-form-actions">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-general-btn">
            <i data-lucide="save" style="width: 15px; height: 15px;"></i>
            <?= __('admin.settings.save_button') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
