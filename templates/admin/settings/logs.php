<?php
$activePage = 'settings';
$activeTab = 'logs';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
$logContent = $logContent ?? '';
?>
<div class="settings-card">
    <h3>Application Logs</h3>
    <p class="card-desc">Most recent 100 lines from <code>storage/logs/app.log</code>.</p>

    <?php if ($logContent !== ''): ?>
        <div class="log-viewer"><?= htmlspecialchars($logContent, ENT_QUOTES, 'UTF-8') ?></div>
    <?php else: ?>
        <div style="text-align:center;padding:3rem 1rem;color:var(--vb-admin-text-ghost);">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 1rem;display:block;"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
            <p style="font-size:var(--vb-text-sm);font-weight:500;color:var(--vb-admin-text-secondary);margin-bottom:0.25rem;">No log entries yet</p>
            <p style="font-size:var(--vb-text-sm);">Application events will appear here once they are recorded.</p>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
