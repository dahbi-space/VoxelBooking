<?php
/**
 * Cron Settings — token and status display.
 *
 * Variables: $user, $version, $csrfToken, $cronToken, $lastRun, $flash, $pageTitle
 */
$activePage = 'settings.cron';
$activeTab = 'cron';

ob_start();

include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>

<?php if ($flash): ?>
    <div class="vb-alert vb-alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="vb-grid" style="gap: 1.5rem; grid-template-columns: 1fr 1fr;">
    <!-- Cron Configuration -->
    <div class="vb-card vb-fade-in-up stagger-1">
        <div class="vb-card-header">
            <div class="vb-card-title">Cron Job</div>
            <div class="vb-card-desc">Add this command to your server's crontab (every 5 minutes recommended).</div>
        </div>

        <div class="vb-form-group">
            <label class="vb-label">Crontab command</label>
            <input type="text" class="vb-input vb-input-mono" readonly value="*/5 * * * * curl -s <?= htmlspecialchars(($_ENV['APP_URL'] ?? 'https://yourdomain.com'), ENT_QUOTES, 'UTF-8') ?>/cron?token=<?= htmlspecialchars($cronToken, ENT_QUOTES, 'UTF-8') ?> > /dev/null 2>&1" onclick="this.select()">
            <div class="vb-hint">Click to select, then copy.</div>
        </div>

        <div class="vb-form-group">
            <label class="vb-label">Cron token</label>
            <input type="text" class="vb-input vb-input-mono" readonly value="<?= htmlspecialchars($cronToken, ENT_QUOTES, 'UTF-8') ?>" onclick="this.select()">
            <div class="vb-hint">Auto-generated. Keep this token secret.</div>
        </div>
    </div>

    <!-- Status -->
    <div class="vb-card vb-fade-in-up stagger-2">
        <div class="vb-card-header">
            <div class="vb-card-title">Status</div>
            <div class="vb-card-desc">Cron job execution history.</div>
        </div>

        <div class="vb-info-row">
            <span class="vb-info-label">Last run</span>
            <span class="vb-info-value">
                <?php if ($lastRun): ?>
                    <code><?= htmlspecialchars($lastRun, ENT_QUOTES, 'UTF-8') ?></code>
                <?php else: ?>
                    <span style="color: var(--vb-admin-text-tertiary);">Never — cron has not run yet</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label">Status</span>
            <span class="vb-info-value">
                <?php if ($lastRun): ?>
                    <span style="color: var(--vb-admin-success); font-weight: 500; display: inline-flex; align-items: center; gap: 0.25rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                        Active
                    </span>
                <?php else: ?>
                    <span style="color: var(--vb-admin-warning); font-weight: 500; display: inline-flex; align-items: center; gap: 0.25rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Not configured
                    </span>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
