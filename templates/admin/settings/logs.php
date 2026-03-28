<?php
/**
 * Log Viewer — tail of app.log.
 *
 * Variables: $user, $version, $csrfToken, $logContent, $pageTitle
 */
$activePage = 'settings.logs';
$activeTab = 'logs';

ob_start();

include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>

<div class="vb-card vb-fade-in-up stagger-1">
    <div class="vb-card-header">
        <div class="vb-card-title"><?= __('admin.logs.title') ?></div>
        <div class="vb-card-desc"><?= __('admin.logs.desc') ?> <code class="vb-code-chip">storage/logs/app.log</code></div>
    </div>

    <?php if (trim($logContent ?? '') !== ''): ?>
        <div class="vb-log-viewer"><?= htmlspecialchars($logContent, ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>
        <div class="vb-table-empty">
            <i data-lucide="file-text" class="vb-table-empty-icon"></i>
            <div class="vb-table-empty-title"><?= __('admin.logs.empty_title') ?></div>
            <div class="vb-table-empty-desc"><?= __('admin.logs.empty_desc') ?></div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
