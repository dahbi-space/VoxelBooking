<?php
/**
 * Tenant Settings — Notifications tab.
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

$isChecked = function (string $field) use ($tenant, $old): string {
    if ($old !== null && array_key_exists($field, $old)) {
        return $old[$field] === '1' ? 'checked' : '';
    }
    return ((int) ($tenant[$field] ?? 0)) === 1 ? 'checked' : '';
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

<form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/settings/notifications" class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-form-grid">
                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-notif-email"><?= __('admin.tenant_settings.field_notif_email') ?></label>
                    <input type="email" class="vb-input" id="ts-notif-email" name="notification_email"
                           value="<?= $val('notification_email') ?>"
                           placeholder="<?= e($tenant['email'] ?? '') ?>">
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_notif_email_hint') ?></span>
                </div>

                <div class="vb-settings-toggles">
                    <label class="vb-settings-toggle-item">
                        <input type="hidden" name="notify_on_booking" value="0">
                        <input type="checkbox" name="notify_on_booking" value="1" <?= $isChecked('notify_on_booking') ?>>
                        <div>
                            <div class="vb-settings-toggle-label"><?= __('admin.tenant_settings.field_notify_booking') ?></div>
                        </div>
                    </label>
                    <label class="vb-settings-toggle-item">
                        <input type="hidden" name="notify_on_cancellation" value="0">
                        <input type="checkbox" name="notify_on_cancellation" value="1" <?= $isChecked('notify_on_cancellation') ?>>
                        <div>
                            <div class="vb-settings-toggle-label"><?= __('admin.tenant_settings.field_notify_cancel') ?></div>
                        </div>
                    </label>
                    <label class="vb-settings-toggle-item">
                        <input type="hidden" name="send_reminders" value="0">
                        <input type="checkbox" name="send_reminders" value="1" <?= $isChecked('send_reminders') ?>>
                        <div>
                            <div class="vb-settings-toggle-label"><?= __('admin.tenant_settings.field_send_reminders') ?></div>
                        </div>
                    </label>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-reminder-hrs"><?= __('admin.tenant_settings.field_reminder_hours') ?></label>
                    <input type="number" class="vb-input" id="ts-reminder-hrs" name="reminder_hours_before"
                           value="<?= $val('reminder_hours_before', '24') ?>"
                           min="1" step="1" style="max-width: 100px;">
                </div>
            </div>
        </div>
    </div>

    <div class="vb-form-actions">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-notifications-btn">
            <i data-lucide="save" style="width: 15px; height: 15px;"></i>
            <?= __('admin.settings.save_button') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
