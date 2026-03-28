<?php
/**
 * Email Settings — SMTP configuration.
 *
 * Variables: $user, $version, $csrfToken, $settings, $flash, $pageTitle
 */
$activePage = 'settings.email';
$activeTab = 'email';

ob_start();

include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>

<?php if ($flash): ?>
    <div class="vb-alert vb-alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?php if ($flash['type'] === 'success'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 6 9 17l-5-5"/></svg>
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <?php endif; ?>
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/settings/email">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="vb-grid" style="gap: 1.5rem; grid-template-columns: 1fr 1fr;">
        <!-- SMTP Configuration -->
        <div class="vb-card vb-fade-in-up stagger-1">
            <div class="vb-card-header">
                <div class="vb-card-title"><?= __('admin.email.smtp_title') ?></div>
                <div class="vb-card-desc"><?= __('admin.email.smtp_desc') ?></div>
            </div>

            <div class="vb-form-group">
                <label for="mail_transport" class="vb-label"><?= __('admin.email.transport_label') ?></label>
                <select id="mail_transport" name="mail_transport" class="vb-input">
                    <option value="smtp" <?= ($settings['mail_transport'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>><?= __('admin.email.transport_smtp') ?></option>
                    <option value="mailpit" <?= ($settings['mail_transport'] ?? '') === 'mailpit' ? 'selected' : '' ?>><?= __('admin.email.transport_mailpit') ?></option>
                    <option value="log" <?= ($settings['mail_transport'] ?? '') === 'log' ? 'selected' : '' ?>><?= __('admin.email.transport_log') ?></option>
                </select>
            </div>

            <div class="vb-form-row">
                <div class="vb-form-group">
                    <label for="smtp_host" class="vb-label"><?= __('admin.email.host_label') ?></label>
                    <input type="text" id="smtp_host" name="smtp_host" class="vb-input" value="<?= htmlspecialchars($settings['smtp_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="smtp.example.com">
                </div>
                <div class="vb-form-group">
                    <label for="smtp_port" class="vb-label"><?= __('admin.email.port_label') ?></label>
                    <input type="number" id="smtp_port" name="smtp_port" class="vb-input" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587', ENT_QUOTES, 'UTF-8') ?>" placeholder="587">
                </div>
            </div>
            <div class="vb-form-group">
                <label for="smtp_username" class="vb-label"><?= __('admin.email.username_label') ?></label>
                <input type="text" id="smtp_username" name="smtp_username" class="vb-input" value="<?= htmlspecialchars($settings['smtp_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="noreply@yourdomain.com" autocomplete="off">
            </div>
            <div class="vb-form-group">
                <label for="smtp_password" class="vb-label"><?= __('admin.email.password_label') ?></label>
                <input type="password" id="smtp_password" name="smtp_password" class="vb-input" placeholder="<?= __('admin.email.password_hint') ?>" autocomplete="new-password">
            </div>
            <div class="vb-form-group">
                <label for="smtp_encryption" class="vb-label"><?= __('admin.email.encryption_label') ?></label>
                <select id="smtp_encryption" name="smtp_encryption" class="vb-input">
                    <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>><?= __('admin.email.tls_recommended') ?></option>
                    <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                </select>
            </div>
        </div>

        <!-- Sender Identity -->
        <div class="vb-card vb-fade-in-up stagger-2">
            <div class="vb-card-header">
                <div class="vb-card-title"><?= __('admin.email.sender_title') ?></div>
                <div class="vb-card-desc"><?= __('admin.email.sender_desc') ?></div>
            </div>

            <div class="vb-form-group">
                <label for="mail_from_name" class="vb-label"><?= __('admin.email.from_name_label') ?></label>
                <input type="text" id="mail_from_name" name="mail_from_name" class="vb-input" value="<?= htmlspecialchars($settings['mail_from_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="VoxelBooking">
            </div>
            <div class="vb-form-group">
                <label for="mail_from_address" class="vb-label"><?= __('admin.email.from_address_label') ?></label>
                <input type="email" id="mail_from_address" name="mail_from_address" class="vb-input" value="<?= htmlspecialchars($settings['mail_from_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="noreply@yourdomain.com">
            </div>
        </div>
    </div>

    <div class="vb-form-actions" style="margin-top: 1.5rem;">
        <button type="submit" class="vb-btn vb-btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?= __('admin.email.save_button') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
