<?php
$activePage = 'settings';
$activeTab = 'cron';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
$cronToken = $cronToken ?? '';
$lastRun = $lastRun ?? '';
$flash = $flash ?? null;
$baseUrl = $_ENV['APP_URL'] ?? 'https://your-domain.com';
?>
<?php if ($flash): ?>
    <div class="flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="settings-card">
    <h3>Cron Configuration</h3>
    <p class="card-desc">VoxelBooking uses a single cron endpoint for booking reminders, data retention cleanup, and rate limit resets.</p>

    <div class="form-group">
        <label class="form-label">Cron URL</label>
        <input type="text" class="form-input form-input-mono" readonly value="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/cron/run?token=<?= htmlspecialchars($cronToken, ENT_QUOTES, 'UTF-8') ?>" id="cron-url">
        <p class="form-hint">Add this URL to your server's crontab. Run every 5 minutes.</p>
    </div>

    <div class="form-group">
        <label class="form-label">Crontab Command</label>
        <input type="text" class="form-input form-input-mono" readonly value="*/5 * * * * curl -s '<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/cron/run?token=<?= htmlspecialchars($cronToken, ENT_QUOTES, 'UTF-8') ?>' > /dev/null 2>&1">
        <p class="form-hint">Copy this line into your server's crontab (<code>crontab -e</code>).</p>
    </div>
</div>

<div class="settings-card">
    <h3>Status</h3>
    <div class="info-row">
        <span class="info-label">Cron Token</span>
        <span class="info-value"><code><?= htmlspecialchars(substr($cronToken, 0, 8) . '…', ENT_QUOTES, 'UTF-8') ?></code></span>
    </div>
    <div class="info-row">
        <span class="info-label">Last Run</span>
        <span class="info-value"><?= $lastRun !== '' ? htmlspecialchars($lastRun, ENT_QUOTES, 'UTF-8') : '<span style="color:var(--vb-admin-text-ghost);">Never</span>' ?></span>
    </div>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
