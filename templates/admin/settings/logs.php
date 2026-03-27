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
        <div class="vb-card-title">Application Log</div>
        <div class="vb-card-desc">Last 100 lines from <code style="font-family: var(--vb-font-mono); font-size: var(--vb-text-xs); padding: 0.125rem 0.375rem; background: var(--vb-admin-bg-well); border-radius: var(--vb-radius-xs);">storage/logs/app.log</code></div>
    </div>

    <?php if (trim($logContent ?? '') !== ''): ?>
        <div class="vb-log-viewer"><?= htmlspecialchars($logContent, ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: var(--vb-admin-text-tertiary);">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 0.75rem; opacity: 0.5;"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
            <div style="font-size: var(--vb-text-sm); font-weight: 500;">No log entries yet</div>
            <div style="font-size: var(--vb-text-xs); margin-top: 0.25rem;">Log entries will appear here as the system operates.</div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
