<?php
/**
 * Admin 403 error page — renders inside admin shell.
 * Uses design system: .vb-empty, .vb-error-code, Lucide icons.
 *
 * Variables: $user, $version, $csrfToken
 */
$pageTitle = __('admin.errors.403_admin_title');
$activePage = '';
$csrfToken = $csrfToken ?? \App\Middleware\CsrfMiddleware::generateToken();

ob_start();
?>

<div class="vb-empty vb-animate-in" style="max-width: 480px; margin: 4rem auto;">
    <i data-lucide="lock" class="vb-empty-icon"></i>
    <div>
        <div class="vb-error-code">403</div>
        <div class="vb-empty-title"><?= __('admin.errors.403_title') ?></div>
        <div class="vb-empty-desc"><?= __('admin.errors.403_desc_admin') ?></div>
        <a href="/admin" class="vb-btn vb-btn-primary vb-btn-lg">
            <i data-lucide="chevron-left"></i>
            <?= __('admin.errors.403_action') ?>
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
