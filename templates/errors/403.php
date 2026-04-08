<?php
/** 403 Forbidden — PRD §XV: lock icon, "Access denied". */
$user = $user ?? null;
?>
<!DOCTYPE html>
<html lang="<?= \App\Engine\Locale::getLocale() ?>" dir="<?= \App\Engine\Locale::direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= __('admin.errors.403_page_title', ['app_name' => app_name()]) ?></title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <style>
        body { font-family: var(--vb-font-sans); background: var(--vb-admin-bg-base); color: var(--vb-admin-text-primary); min-height: 100vh; display: flex; align-items: center; justify-content: center; -webkit-font-smoothing: antialiased; }
        .error-page { text-align: center; max-width: 400px; padding: 2rem; }
        .error-icon { color: var(--vb-admin-warning); margin-bottom: 1.5rem; }
        .error-code { font-size: 3rem; font-weight: 700; letter-spacing: -0.05em; color: var(--vb-admin-text-tertiary); margin-bottom: 0.5rem; }
        .error-title { font-size: var(--vb-text-xl); font-weight: 600; margin-bottom: 0.5rem; }
        .error-desc { font-size: var(--vb-text-base); color: var(--vb-admin-text-secondary); margin-bottom: 2rem; }
        .error-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-family: var(--vb-font-sans); font-size: var(--vb-text-sm); font-weight: 600; color: #fff; background: var(--vb-admin-accent); border: none; border-radius: var(--vb-radius-sm); text-decoration: none; transition: background 150ms; }
        .error-btn:hover { background: var(--vb-admin-accent-hover); }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-icon">
            <!-- Lucide lock -->
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="error-code">403</div>
        <h1 class="error-title"><?= __('admin.errors.403_title') ?></h1>
        <p class="error-desc"><?= __('admin.errors.403_desc') ?></p>
        <a href="/admin" class="error-btn"><?= __('admin.errors.403_action') ?></a>
    </div>
</body>
</html>
