<?php
/**
 * Admin 403 error page — renders inside admin shell.
 *
 * Variables: $user, $version, $csrfToken
 */
$pageTitle = __('admin.errors.403_admin_title');
$activePage = '';
$csrfToken = $csrfToken ?? \App\Middleware\CsrfMiddleware::generateToken();

ob_start();
?>

<div class="vb-empty vb-fade-in-up" style="max-width: 480px; margin: 4rem auto;">
    <svg class="vb-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
    <div>
        <div style="font-size: var(--vb-text-3xl); font-weight: 700; color: var(--vb-admin-text-ghost); letter-spacing: var(--vb-tracking-tight); margin-bottom: 0.25rem; font-variant-numeric: tabular-nums;">403</div>
        <div class="vb-empty-title"><?= __('admin.errors.403_title') ?></div>
        <div class="vb-empty-desc"><?= __('admin.errors.403_desc_admin') ?></div>
        <a href="/admin" class="vb-btn vb-btn-primary vb-btn-lg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            <?= __('admin.errors.403_action') ?>
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
