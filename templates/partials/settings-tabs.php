<?php
/**
 * Settings Tab Navigation — Visual Design §7.2 / §12.10.
 *
 * Uses .vb-tabs / .vb-tab classes from admin-head.php.
 * Icons: Lucide via data-lucide attribute (tree-shaken in app.js).
 *
 * Renders the canonical page header + tab bar for all operator settings pages.
 * Matches the tenant settings pattern (page-header → tabs → content).
 *
 * Variables: $activeTab (string: general|email|cron|logs|audit)
 */
$activeTab = $activeTab ?? 'general';
$tabs = [
    'general' => ['label' => __('admin.tabs.general'), 'href' => '/admin/settings', 'icon' => 'settings'],
    'email'   => ['label' => __('admin.tabs.email'), 'href' => '/admin/settings/email', 'icon' => 'mail'],
    'cron'    => ['label' => __('admin.tabs.cron'), 'href' => '/admin/settings/cron', 'icon' => 'clock'],
    'logs'    => ['label' => __('admin.tabs.logs'), 'href' => '/admin/settings/logs', 'icon' => 'file-text'],
    'audit'   => ['label' => __('admin.tabs.audit'), 'href' => '/admin/settings/audit', 'icon' => 'shield'],
];
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.settings.title') ?></h2>
        <p class="vb-page-subtitle"><?= __('admin.settings.subtitle') ?></p>
    </div>
</div>

<nav class="vb-tabs" aria-label="<?= __('admin.tabs.aria') ?>">
    <?php foreach ($tabs as $key => $tab): ?>
        <a href="<?= $tab['href'] ?>" class="vb-tab <?= $activeTab === $key ? 'active' : '' ?>">
            <i data-lucide="<?= $tab['icon'] ?>"></i>
            <?= $tab['label'] ?>
        </a>
    <?php endforeach; ?>
</nav>
