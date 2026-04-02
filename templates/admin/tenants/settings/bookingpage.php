<?php
/**
 * Tenant Settings — Booking Page tab.
 *
 * Surfaces: confirmation_message, cancellation_policy, require_phone,
 * booking_requires_approval, allow_cancellation, cancellation_hours_before,
 * allow_rescheduling, rescheduling_hours_before.
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
    <?php include __DIR__ . '/../../../partials/alert.php'; ?>
<?php endif; ?>

<?php include __DIR__ . '/_tabs.php'; ?>

<form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/settings/bookingpage" class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Section 1: Content -->
    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-card-header">
                <div class="vb-card-title"><?= __('admin.tenant_settings.bookingpage_section_content') ?></div>
                <div class="vb-card-desc"><?= __('admin.tenant_settings.bookingpage_section_content_desc') ?></div>
            </div>
            <div class="vb-form-grid">
                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-confirmation-msg"><?= __('admin.tenant_settings.field_confirmation_message') ?></label>
                    <textarea class="vb-input" id="ts-confirmation-msg" name="confirmation_message"
                              rows="3" placeholder="<?= __('admin.tenant_settings.field_confirmation_message_placeholder') ?>"><?= $val('confirmation_message') ?></textarea>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_confirmation_message_hint') ?></span>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-cancel-policy"><?= __('admin.tenant_settings.field_cancellation_policy') ?></label>
                    <textarea class="vb-input" id="ts-cancel-policy" name="cancellation_policy"
                              rows="3" placeholder="<?= __('admin.tenant_settings.field_cancellation_policy_placeholder') ?>"><?= $val('cancellation_policy') ?></textarea>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_cancellation_policy_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Rules & Policies -->
    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-card-header">
                <div class="vb-card-title"><?= __('admin.tenant_settings.bookingpage_section_rules') ?></div>
                <div class="vb-card-desc"><?= __('admin.tenant_settings.bookingpage_section_rules_desc') ?></div>
            </div>
            <div class="vb-form-grid">
                <div class="vb-settings-toggles">
                    <label class="vb-settings-toggle-item">
                        <input type="hidden" name="require_phone" value="0">
                        <input type="checkbox" name="require_phone" value="1" <?= $isChecked('require_phone') ?>>
                        <div>
                            <div class="vb-settings-toggle-label"><?= __('admin.tenant_settings.field_require_phone') ?></div>
                            <div class="vb-settings-toggle-hint"><?= __('admin.tenant_settings.field_require_phone_hint') ?></div>
                        </div>
                    </label>
                    <label class="vb-settings-toggle-item">
                        <input type="hidden" name="booking_requires_approval" value="0">
                        <input type="checkbox" name="booking_requires_approval" value="1" <?= $isChecked('booking_requires_approval') ?>>
                        <div>
                            <div class="vb-settings-toggle-label"><?= __('admin.tenant_settings.field_booking_requires_approval') ?></div>
                            <div class="vb-settings-toggle-hint"><?= __('admin.tenant_settings.field_booking_requires_approval_hint') ?></div>
                        </div>
                    </label>
                </div>

                <!-- Cancellation -->
                <div class="vb-settings-toggles" style="margin-top: 1rem;">
                    <label class="vb-settings-toggle-item">
                        <input type="hidden" name="allow_cancellation" value="0">
                        <input type="checkbox" name="allow_cancellation" value="1" <?= $isChecked('allow_cancellation') ?>>
                        <div>
                            <div class="vb-settings-toggle-label"><?= __('admin.tenant_settings.field_allow_cancellation') ?></div>
                            <div class="vb-settings-toggle-hint"><?= __('admin.tenant_settings.field_allow_cancellation_hint') ?></div>
                        </div>
                    </label>
                </div>
                <div class="vb-settings-field" style="margin-left: 2rem;">
                    <label class="vb-label" for="ts-cancel-hours"><?= __('admin.tenant_settings.field_cancellation_hours_before') ?></label>
                    <input type="number" class="vb-input" id="ts-cancel-hours" name="cancellation_hours_before"
                           value="<?= $val('cancellation_hours_before', '24') ?>"
                           min="0" step="1" style="max-width: 100px;">
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_cancellation_hours_before_hint') ?></span>
                </div>

                <!-- Rescheduling -->
                <div class="vb-settings-toggles" style="margin-top: 1rem;">
                    <label class="vb-settings-toggle-item">
                        <input type="hidden" name="allow_rescheduling" value="0">
                        <input type="checkbox" name="allow_rescheduling" value="1" <?= $isChecked('allow_rescheduling') ?>>
                        <div>
                            <div class="vb-settings-toggle-label"><?= __('admin.tenant_settings.field_allow_rescheduling') ?></div>
                            <div class="vb-settings-toggle-hint"><?= __('admin.tenant_settings.field_allow_rescheduling_hint') ?></div>
                        </div>
                    </label>
                </div>
                <div class="vb-settings-field" style="margin-left: 2rem;">
                    <label class="vb-label" for="ts-reschedule-hours"><?= __('admin.tenant_settings.field_rescheduling_hours_before') ?></label>
                    <input type="number" class="vb-input" id="ts-reschedule-hours" name="rescheduling_hours_before"
                           value="<?= $val('rescheduling_hours_before', '24') ?>"
                           min="0" step="1" style="max-width: 100px;">
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_rescheduling_hours_before_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="vb-form-actions">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-bookingpage-btn">
            <i data-lucide="save" style="width: 15px; height: 15px;"></i>
            <?= __('admin.settings.save_button') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
