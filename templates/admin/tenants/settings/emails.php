<?php
/**
 * Tenant Settings — Email Templates tab.
 *
 * Per PRD §VII — tenant-scoped transactional email customization.
 * Tenant customizes subject, heading, intro, outro, CTA label per email type.
 * When a row doesn't exist, system defaults are shown as placeholders.
 *
 * Variables: $tenant, $tenantId, $csrfToken, $activeTab, $flash, $old, $templates
 */
$tenant    = $tenant ?? [];
$tenantId  = $tenantId ?? '';
$templates = $templates ?? [];
$old       = $old ?? null;

// Index templates by type for easy lookup
$byType = [];
foreach ($templates as $tpl) {
    $byType[$tpl['type']] = $tpl;
}

// Define supported email types with their system defaults (PRD §VII — 7 types)
$emailTypes = [
    'confirmation' => [
        'label'   => 'Booking Confirmation',
        'desc'    => 'Sent to customers after a booking is confirmed.',
        'defaults' => [
            'subject'    => 'Your {service_name} booking on {booking_date}',
            'heading'    => 'Booking confirmed',
            'body_intro' => 'Thank you for your booking.',
            'body_outro' => 'If you need to make changes, please contact us.',
            'cta_label'  => 'View Booking',
        ],
    ],
    'reminder' => [
        'label'   => 'Booking Reminder',
        'desc'    => 'Sent before the appointment (when reminders are enabled).',
        'defaults' => [
            'subject'    => 'Reminder: {service_name} tomorrow at {booking_time}',
            'heading'    => 'Appointment Reminder',
            'body_intro' => 'This is a reminder for your upcoming appointment.',
            'body_outro' => '',
            'cta_label'  => 'View Booking',
        ],
    ],
    'cancellation' => [
        'label'   => 'Cancellation Confirmation',
        'desc'    => 'Sent when a booking is cancelled.',
        'defaults' => [
            'subject'    => 'Booking cancelled — {business_name}',
            'heading'    => 'Booking Cancelled',
            'body_intro' => 'Your booking has been cancelled.',
            'body_outro' => 'If this was a mistake, please contact us to rebook.',
            'cta_label'  => 'Book Again',
        ],
    ],
    'reschedule_confirmation' => [
        'label'   => 'Reschedule Confirmation',
        'desc'    => 'Sent when a booking is rescheduled to a new time.',
        'defaults' => [
            'subject'    => 'Booking rescheduled — {business_name}',
            'heading'    => 'Booking Rescheduled',
            'body_intro' => 'Your booking has been rescheduled.',
            'body_outro' => '',
            'cta_label'  => 'View Booking',
        ],
    ],
    'approval_request' => [
        'label'   => 'Approval Request',
        'desc'    => 'Sent to customers when their booking requires approval.',
        'defaults' => [
            'subject'    => 'Your booking request has been received — {business_name}',
            'heading'    => 'Request Received',
            'body_intro' => 'Your booking is pending approval. We will notify you once it is confirmed.',
            'body_outro' => '',
            'cta_label'  => '',
        ],
    ],
    'approval_confirmed' => [
        'label'   => 'Approval Confirmed',
        'desc'    => 'Sent to customers when a pending booking is approved.',
        'defaults' => [
            'subject'    => 'Your booking has been approved — {business_name}',
            'heading'    => 'Booking Approved',
            'body_intro' => 'Your booking has been approved and is now confirmed.',
            'body_outro' => '',
            'cta_label'  => 'Add to Calendar',
        ],
    ],
];

$getVal = function (string $type, string $field) use ($byType, $old): string {
    $key = "{$type}_{$field}";
    if ($old !== null && array_key_exists($key, $old)) {
        return htmlspecialchars((string) $old[$key], ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string) ($byType[$type][$field] ?? ''), ENT_QUOTES, 'UTF-8');
};

$isTypeEnabled = function (string $type) use ($byType, $old): bool {
    $key = "{$type}_is_enabled";
    if ($old !== null && array_key_exists($key, $old)) {
        return $old[$key] === '1';
    }
    if (isset($byType[$type])) {
        return (int) ($byType[$type]['is_enabled'] ?? 1) === 1;
    }
    return true; // default enabled
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

<form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/settings/emails" class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="vb-settings-hint-block" style="margin-bottom: 1.5rem;">
        <i data-lucide="info" style="width: 16px; height: 16px; flex-shrink: 0;"></i>
        <div>
            <?= __('admin.tenant_settings.emails_section_desc') ?>
            <br><small style="opacity: 0.7;"><?= __('admin.tenant_settings.emails_placeholders_hint') ?></small>
        </div>
    </div>

    <?php foreach ($emailTypes as $type => $meta): ?>
    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-card-header" style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div class="vb-card-title"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="vb-card-desc"><?= htmlspecialchars($meta['desc'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <label class="vb-settings-toggle-item" style="margin: 0; padding: 0;">
                    <input type="hidden" name="<?= $type ?>_is_enabled" value="0">
                    <input type="checkbox" name="<?= $type ?>_is_enabled" value="1" <?= $isTypeEnabled($type) ? 'checked' : '' ?>>
                </label>
            </div>
            <div class="vb-form-grid" style="gap: 0.75rem;">
                <div class="vb-settings-field">
                    <label class="vb-label" for="tpl-<?= $type ?>-subject"><?= __('admin.tenant_settings.email_field_subject') ?></label>
                    <input type="text" class="vb-input" id="tpl-<?= $type ?>-subject" name="<?= $type ?>_subject"
                           value="<?= $getVal($type, 'subject') ?>"
                           placeholder="<?= htmlspecialchars($meta['defaults']['subject'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="vb-settings-field">
                    <label class="vb-label" for="tpl-<?= $type ?>-heading"><?= __('admin.tenant_settings.email_field_heading') ?></label>
                    <input type="text" class="vb-input" id="tpl-<?= $type ?>-heading" name="<?= $type ?>_heading"
                           value="<?= $getVal($type, 'heading') ?>"
                           placeholder="<?= htmlspecialchars($meta['defaults']['heading'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="vb-settings-field">
                    <label class="vb-label" for="tpl-<?= $type ?>-intro"><?= __('admin.tenant_settings.email_field_intro') ?></label>
                    <textarea class="vb-input" id="tpl-<?= $type ?>-intro" name="<?= $type ?>_body_intro"
                              rows="2" placeholder="<?= htmlspecialchars($meta['defaults']['body_intro'], ENT_QUOTES, 'UTF-8') ?>"><?= $getVal($type, 'body_intro') ?></textarea>
                </div>
                <div class="vb-settings-field">
                    <label class="vb-label" for="tpl-<?= $type ?>-outro"><?= __('admin.tenant_settings.email_field_outro') ?></label>
                    <textarea class="vb-input" id="tpl-<?= $type ?>-outro" name="<?= $type ?>_body_outro"
                              rows="2" placeholder="<?= htmlspecialchars($meta['defaults']['body_outro'], ENT_QUOTES, 'UTF-8') ?>"><?= $getVal($type, 'body_outro') ?></textarea>
                </div>
                <div class="vb-settings-field">
                    <label class="vb-label" for="tpl-<?= $type ?>-cta"><?= __('admin.tenant_settings.email_field_cta') ?></label>
                    <input type="text" class="vb-input" id="tpl-<?= $type ?>-cta" name="<?= $type ?>_cta_label"
                           value="<?= $getVal($type, 'cta_label') ?>"
                           placeholder="<?= htmlspecialchars($meta['defaults']['cta_label'], ENT_QUOTES, 'UTF-8') ?>"
                           style="max-width: 220px;">
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="vb-form-actions">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-emails-btn">
            <i data-lucide="save" style="width: 15px; height: 15px;"></i>
            <?= __('admin.settings.save_button') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
