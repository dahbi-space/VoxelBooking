<?php
/**
 * Cron Settings — token, status display, and "Run now" action.
 *
 * Variables: $user, $version, $csrfToken, $cronToken, $lastRun, $flash, $pageTitle
 */
$activePage = 'settings.cron';
$activeTab = 'cron';

ob_start();

include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>

<?php if ($flash): ?>
    <?php include __DIR__ . '/../../partials/alert.php'; ?>
<?php endif; ?>

<div class="vb-grid vb-grid-2">
    <!-- Cron Configuration -->
    <div class="vb-card vb-fade-in-up stagger-1">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.cron.job_title') ?></div>
            <div class="vb-card-desc"><?= __('admin.cron.job_desc') ?></div>
        </div>

        <div class="vb-form-group">
            <label class="vb-label"><?= __('admin.cron.command_label') ?></label>
            <input type="text" class="vb-input vb-input-mono" readonly value="*/5 * * * * curl -s <?= htmlspecialchars(($_ENV['APP_URL'] ?? 'https://yourdomain.com'), ENT_QUOTES, 'UTF-8') ?>/cron/run?token=<?= htmlspecialchars($cronToken, ENT_QUOTES, 'UTF-8') ?> > /dev/null 2>&1" onclick="this.select()">
            <div class="vb-hint"><?= __('admin.cron.command_hint') ?></div>
        </div>

        <div class="vb-form-group">
            <label class="vb-label"><?= __('admin.cron.token_label') ?></label>
            <input type="text" class="vb-input vb-input-mono" readonly value="<?= htmlspecialchars($cronToken, ENT_QUOTES, 'UTF-8') ?>" onclick="this.select()">
            <div class="vb-hint"><?= __('admin.cron.token_hint') ?></div>
        </div>

        <div class="vb-form-group">
            <label class="vb-label"><?= __('admin.cron.tasks_title') ?></label>
            <div class="vb-hint" style="margin-top: 0;">
                <ul style="margin: 0.25rem 0 0 1.25rem; padding: 0; list-style: disc;">
                    <li><?= __('admin.cron.task_retention') ?></li>
                    <li><?= __('admin.cron.task_reminders') ?></li>
                    <li><?= __('admin.cron.task_audit') ?></li>
                    <li><?= __('admin.cron.task_email_log') ?></li>
                    <li><?= __('admin.cron.task_rate_limits') ?></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Status -->
    <div class="vb-card vb-fade-in-up stagger-2">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.cron.status_title') ?></div>
            <div class="vb-card-desc"><?= __('admin.cron.status_desc') ?></div>
        </div>

        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.cron.last_run') ?></span>
            <span class="vb-info-value">
                <?php if ($lastRun): ?>
                    <code><?= htmlspecialchars($lastRun, ENT_QUOTES, 'UTF-8') ?></code>
                <?php else: ?>
                    <span class="vb-text-tertiary"><?= __('admin.cron.never_run') ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.cron.status_label') ?></span>
            <span class="vb-info-value">
                <?php if ($lastRun): ?>
                    <span class="vb-badge vb-badge-success vb-badge-inline">
                        <i data-lucide="check" class="vb-badge-icon"></i>
                        <?= __('admin.cron.active') ?>
                    </span>
                <?php else: ?>
                    <span class="vb-badge vb-badge-warning vb-badge-inline">
                        <i data-lucide="alert-circle" class="vb-badge-icon"></i>
                        <?= __('admin.cron.not_configured') ?>
                    </span>
                <?php endif; ?>
            </span>
        </div>

        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--vb-border, #e5e7eb);">
            <form method="POST" action="/admin/settings/cron/run" style="display: inline;">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="vb-btn vb-btn-secondary" id="cron-run-now-btn">
                    <i data-lucide="play" style="width: 15px; height: 15px;"></i>
                    <?= __('admin.cron.run_now') ?>
                </button>
            </form>
            <div class="vb-hint" style="margin-top: 0.5rem;"><?= __('admin.cron.run_now_hint') ?></div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
