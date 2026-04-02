<?php
/**
 * Settings tab navigation — uses design system .vb-tabs-line component.
 *
 * Variables: $tenantId, $activeTab, $tenant
 */
$tenantId  = $tenantId ?? '';
$activeTab = $activeTab ?? 'general';
$tenant    = $tenant ?? [];

$tabs = [
    'general'  => ['label' => __('admin.tenant_settings.tab_general'),  'icon' => 'building-2', 'href' => "/admin/tenants/{$tenantId}/settings"],
    'branding' => ['label' => __('admin.tenant_settings.tab_branding'), 'icon' => 'palette',    'href' => "/admin/tenants/{$tenantId}/settings/branding"],
    'bookingpage' => ['label' => __('admin.tenant_settings.tab_bookingpage'), 'icon' => 'file-text', 'href' => "/admin/tenants/{$tenantId}/settings/bookingpage"],
];

// Booking Rules tab — only for timeslot pattern tenants
if (($tenant['booking_pattern'] ?? '') === 'timeslot') {
    $tabs['booking'] = [
        'label' => __('admin.tenant_settings.tab_booking'),
        'icon'  => 'clock',
        'href'  => "/admin/tenants/{$tenantId}/settings/booking",
    ];
}

$tabs['privacy']       = ['label' => __('admin.tenant_settings.tab_privacy'),       'icon' => 'shield-check', 'href' => "/admin/tenants/{$tenantId}/settings/privacy"];
$tabs['notifications'] = ['label' => __('admin.tenant_settings.tab_notifications'), 'icon' => 'bell',         'href' => "/admin/tenants/{$tenantId}/settings/notifications"];
$tabs['emails']        = ['label' => __('admin.tenant_settings.tab_emails'),        'icon' => 'mail',         'href' => "/admin/tenants/{$tenantId}/settings/emails"];
?>

<div class="vb-tabs-line" style="margin-top: 1.25rem; overflow-x: auto; -webkit-overflow-scrolling: touch;">
    <?php foreach ($tabs as $key => $tab): ?>
    <a href="<?= htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8') ?>"
       class="vb-tab <?= $activeTab === $key ? 'active' : '' ?>"
       id="tab-<?= $key ?>">
        <i data-lucide="<?= $tab['icon'] ?>" style="width: 15px; height: 15px;"></i>
        <?= $tab['label'] ?>
    </a>
    <?php endforeach; ?>
</div>
