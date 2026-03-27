<?php
$activePage = 'settings';
$activeTab = 'account';
ob_start();
include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
$flash = $flash ?? null;
?>
<?php if ($flash): ?>
    <div class="flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="settings-card">
    <h3>Account Details</h3>
    <p class="card-desc">Your operator account information.</p>
    <div class="info-row">
        <span class="info-label">Name</span>
        <span class="info-value"><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Email</span>
        <span class="info-value"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Role</span>
        <span class="info-value"><?= ($user['type'] ?? '') === 'operator' ? 'Operator (full access)' : 'Business user' ?></span>
    </div>
</div>

<form method="POST" action="/admin/settings/account">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="settings-card">
        <h3>Change Password</h3>
        <p class="card-desc">Update your login password. Minimum 8 characters.</p>

        <div class="form-group">
            <label class="form-label" for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" class="form-input" required autocomplete="current-password" style="max-width:24rem;">
        </div>
        <div class="form-row" style="max-width:24rem;">
            <div class="form-group">
                <label class="form-label" for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-input" required minlength="8" autocomplete="new-password">
                <div id="pw-strength" style="height:4px;border-radius:2px;margin-top:0.375rem;background:var(--vb-admin-border-subtle);overflow:hidden;">
                    <div id="pw-bar" style="height:100%;width:0;transition:width 200ms,background 200ms;border-radius:2px;"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="8" autocomplete="new-password">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Update password
    </button>
</form>

<script>
(function() {
    var pw = document.getElementById('new_password');
    var bar = document.getElementById('pw-bar');
    if (!pw || !bar) return;
    pw.addEventListener('input', function() {
        var v = pw.value, s = 0;
        if (v.length >= 8) s++;
        if (v.length >= 12) s++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
        if (/[0-9]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v)) s++;
        var pct = Math.min(s / 4 * 100, 100);
        bar.style.width = pct + '%';
        bar.style.background = pct < 40 ? '#e53e3e' : pct < 75 ? '#d69e2e' : '#38a169';
    });
})();
</script>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
