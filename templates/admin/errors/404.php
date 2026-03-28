<?php
/**
 * Admin 404 error page — renders inside admin shell.
 * Visual Design §14: no giant dead empty canvas.
 *
 * Variables: $user, $version, $csrfToken
 */
$pageTitle = __('admin.errors.404_admin_title');
$activePage = '';
$csrfToken = $csrfToken ?? \App\Middleware\CsrfMiddleware::generateToken();

ob_start();
?>

<div class="vb-empty vb-fade-in-up" style="max-width: 480px; margin: 4rem auto;">
    <svg class="vb-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
    </svg>
    <div>
        <div style="font-size: var(--vb-text-3xl); font-weight: 700; color: var(--vb-admin-text-ghost); letter-spacing: var(--vb-tracking-tight); margin-bottom: 0.25rem; font-variant-numeric: tabular-nums;">404</div>
        <div class="vb-empty-title"><?= __('admin.errors.404_title') ?></div>
        <div class="vb-empty-desc"><?= __('admin.errors.404_desc_admin') ?></div>
        <a href="/admin" class="vb-btn vb-btn-primary vb-btn-lg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            <?= __('admin.errors.403_action') ?>
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
