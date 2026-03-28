<?php
/**
 * Account Settings — password change + account info.
 *
 * Variables: $user, $version, $csrfToken, $flash, $pageTitle
 */
$activePage = 'settings.account';
$activeTab = 'account';
$currentUser = $user ?? [];

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

<div class="vb-grid" style="gap: 1.5rem; grid-template-columns: 1fr 1fr;">
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
            <span class="vb-info-value" style="text-transform: capitalize;">
                <?= htmlspecialchars($currentUser['type'] ?? 'operator', ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>

    <!-- Change Password -->
    <div class="vb-card vb-fade-in-up stagger-2">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.account.change_pw_title') ?></div>
            <div class="vb-card-desc"><?= __('admin.account.change_pw_desc') ?></div>
        </div>
        <form method="POST" action="/admin/settings/account">
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
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
            fill.style.background = pct <= 20 ? 'var(--vb-admin-error)' : pct <= 60 ? 'var(--vb-admin-warning)' : 'var(--vb-admin-success)';
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
