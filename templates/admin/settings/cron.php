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

<div class="vb-grid vb-grid-2">
    <!-- Cron Configuration -->
    <div class="vb-card vb-fade-in-up stagger-1">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.cron.job_title') ?></div>
            <div class="vb-card-desc"><?= __('admin.cron.job_desc') ?></div>
        </div>

        <div class="vb-form-group">
            <label class="vb-label"><?= __('admin.cron.command_label') ?></label>
            <input type="text" class="vb-input vb-input-mono" readonly value="*/5 * * * * curl -s <?= htmlspecialchars(($_ENV['APP_URL'] ?? 'https://yourdomain.com'), ENT_QUOTES, 'UTF-8') ?>/cron?token=<?= htmlspecialchars($cronToken, ENT_QUOTES, 'UTF-8') ?> > /dev/null 2>&1" onclick="this.select()">
            <div class="vb-hint"><?= __('admin.cron.command_hint') ?></div>
        </div>

        <div class="vb-form-group">
            <label class="vb-label"><?= __('admin.cron.token_label') ?></label>
            <input type="text" class="vb-input vb-input-mono" readonly value="<?= htmlspecialchars($cronToken, ENT_QUOTES, 'UTF-8') ?>" onclick="this.select()">
            <div class="vb-hint"><?= __('admin.cron.token_hint') ?></div>
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
                    <span style="color: var(--vb-admin-text-tertiary);"><?= __('admin.cron.never_run') ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.cron.status_label') ?></span>
            <span class="vb-info-value">
                <?php if ($lastRun): ?>
                    <span class="vb-badge vb-badge-success" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                        <i data-lucide="check" style="width: 13px; height: 13px;"></i>
                        <?= __('admin.cron.active') ?>
                    </span>
                <?php else: ?>
                    <span class="vb-badge vb-badge-warning" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                        <i data-lucide="alert-circle" style="width: 13px; height: 13px;"></i>
                        <?= __('admin.cron.not_configured') ?>
                    </span>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
