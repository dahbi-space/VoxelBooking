<?php
/**
 * Account page — standalone personal surface for credential management.
 *
 * Separated from system settings: operators and business users can manage
 * their own identity here without navigating into the settings namespace.
 *
 * Variables: $user, $version, $csrfToken, $flash, $pageTitle
 */
$activePage = 'account';
$currentUser = $user ?? [];

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.account.page_title') ?></h2>
        <p class="vb-page-subtitle"><?= __('admin.account.page_subtitle') ?></p>
    </div>
</div>

<?php if ($flash): ?>
    <?php include __DIR__ . '/../../partials/alert.php'; ?>
<?php endif; ?>

<div class="vb-grid vb-grid-2">
    <!-- Account Details -->
    <div class="vb-card vb-fade-in-up stagger-1">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.account.details_title') ?></div>
            <div class="vb-card-desc"><?= __('admin.account.details_desc') ?></div>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.account.name_label') ?></span>
            <span class="vb-info-value"><?= htmlspecialchars($currentUser['name'] ?? __('admin.layout.operator'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.account.email_label') ?></span>
            <span class="vb-info-value"><?= htmlspecialchars($currentUser['email'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.account.role_label') ?></span>
            <span class="vb-info-value">
                <span class="vb-badge vb-badge-primary vb-capitalize">
                    <?= htmlspecialchars($currentUser['type'] ?? 'operator', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </span>
        </div>
    </div>

    <!-- Change Password -->
    <div class="vb-card vb-fade-in-up stagger-2">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.account.change_pw_title') ?></div>
            <div class="vb-card-desc"><?= __('admin.account.change_pw_desc') ?></div>
        </div>
        <form method="POST" action="/admin/account">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="vb-form-group">
                <label for="current_password" class="vb-label"><?= __('admin.account.current_pw_label') ?></label>
                <input type="password" id="current_password" name="current_password" class="vb-input" required autocomplete="current-password">
            </div>
            <div class="vb-form-group">
                <label for="new_password" class="vb-label"><?= __('admin.account.new_pw_label') ?></label>
                <input type="password" id="new_password" name="new_password" class="vb-input" required autocomplete="new-password" minlength="8">
                <div class="vb-pw-track" id="pw-track">
                    <div class="vb-pw-fill" id="pw-fill"></div>
                </div>
            </div>
            <div class="vb-form-group">
                <label for="confirm_password" class="vb-label"><?= __('admin.account.confirm_pw_label') ?></label>
                <input type="password" id="confirm_password" name="confirm_password" class="vb-input" required autocomplete="new-password">
            </div>
            <div class="vb-form-actions">
                <button type="submit" class="vb-btn vb-btn-primary">
                    <i data-lucide="lock"></i>
                    <?= __('admin.account.update_pw_button') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var pw = document.getElementById('new_password');
    var fill = document.getElementById('pw-fill');
    if (pw && fill) {
        pw.addEventListener('input', function() {
            var s = 0, v = this.value;
            if (v.length >= 8) s++;
            if (v.length >= 12) s++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
            if (/\d/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            var pct = Math.min(s * 20, 100);
            fill.style.width = pct + '%';
            fill.style.background = pct <= 20 ? 'var(--vb-error)' : pct <= 60 ? 'var(--vb-warning)' : 'var(--vb-success)';
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
