<?php
/**
 * Settings Tab Navigation — Visual Design §7.2.
 *
 * Uses .vb-tabs / .vb-tab classes from admin-head.php.
 * Icons: Lucide via data-lucide attribute (tree-shaken in app.js).
 *
 * Variables: $activeTab (string: general|account|email|cron|logs|audit)
 */
$activeTab = $activeTab ?? 'general';
$tabs = [
    'general' => ['label' => __('admin.tabs.general'), 'href' => '/admin/settings', 'icon' => 'settings'],
    'account' => ['label' => __('admin.tabs.account'), 'href' => '/admin/settings/account', 'icon' => 'user'],
    'email'   => ['label' => __('admin.tabs.email'), 'href' => '/admin/settings/email', 'icon' => 'mail'],
    'cron'    => ['label' => __('admin.tabs.cron'), 'href' => '/admin/settings/cron', 'icon' => 'clock'],
    'logs'    => ['label' => __('admin.tabs.logs'), 'href' => '/admin/settings/logs', 'icon' => 'file-text'],
    'audit'   => ['label' => __('admin.tabs.audit'), 'href' => '/admin/settings/audit', 'icon' => 'shield'],
];
?>

<nav class="vb-tabs" aria-label="<?= __('admin.tabs.aria') ?>">
    <?php foreach ($tabs as $key => $tab): ?>
        <a href="<?= $tab['href'] ?>" class="vb-tab <?= $activeTab === $key ? 'active' : '' ?>">
            <i data-lucide="<?= $tab['icon'] ?>"></i>
            <?= $tab['label'] ?>
        </a>
    <?php endforeach; ?>
</nav>
