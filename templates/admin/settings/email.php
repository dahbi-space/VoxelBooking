<?php
$activePage = 'settings';
$activeTab = 'email';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
$flash = $flash ?? null;
$settings = $settings ?? [];
?>
<?php if ($flash): ?>
    <div class="flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/settings/email">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="settings-card">
        <h3>SMTP Configuration</h3>
        <p class="card-desc">Configure the mail server for sending booking confirmations, reminders, and notifications.</p>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="smtp_host">SMTP Host</label>
                <input type="text" id="smtp_host" name="smtp_host" class="form-input" value="<?= htmlspecialchars($settings['smtp_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="smtp.example.com">
            </div>
            <div class="form-group">
                <label class="form-label" for="smtp_port">SMTP Port</label>
                <input type="number" id="smtp_port" name="smtp_port" class="form-input" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587', ENT_QUOTES, 'UTF-8') ?>" placeholder="587">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="smtp_username">Username</label>
                <input type="text" id="smtp_username" name="smtp_username" class="form-input" value="<?= htmlspecialchars($settings['smtp_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="user@example.com" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label" for="smtp_password">Password</label>
                <input type="password" id="smtp_password" name="smtp_password" class="form-input" placeholder="<?= ($settings['smtp_host'] ?? '') !== '' ? '••••••••' : '' ?>" autocomplete="new-password">
                <p class="form-hint">Leave blank to keep the current password.</p>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="smtp_encryption">Encryption</label>
            <select id="smtp_encryption" name="smtp_encryption" class="form-select" style="max-width:12rem;">
                <?php $enc = $settings['smtp_encryption'] ?? 'tls'; ?>
                <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>TLS (recommended)</option>
                <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>None</option>
            </select>
        </div>
    </div>

    <div class="settings-card">
        <h3>Sender Identity</h3>
        <p class="card-desc">The name and email address shown in outgoing messages.</p>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="mail_from_name">From Name</label>
                <input type="text" id="mail_from_name" name="mail_from_name" class="form-input" value="<?= htmlspecialchars($settings['mail_from_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="VoxelBooking">
            </div>
            <div class="form-group">
                <label class="form-label" for="mail_from_address">From Email</label>
                <input type="email" id="mail_from_address" name="mail_from_address" class="form-input" value="<?= htmlspecialchars($settings['mail_from_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="noreply@example.com">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save email settings
    </button>
</form>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
