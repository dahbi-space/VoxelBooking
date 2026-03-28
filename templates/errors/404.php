<?php
/**
 * 404 Not Found — standalone branded error page.
 * PRD §XV: search icon, "Page not found", "Back to dashboard" button.
 */
$user = $user ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>404 — VoxelBooking</title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <style>
        body {
            font-family: var(--vb-font-sans); background: var(--vb-admin-bg-base);
            color: var(--vb-admin-text-primary); min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            -webkit-font-smoothing: antialiased;
        }
        .error-page { text-align: center; max-width: 400px; padding: 2rem; }
        .error-icon { color: var(--vb-admin-text-ghost); margin-bottom: 1.5rem; }
        .error-code { font-size: 3rem; font-weight: 700; letter-spacing: -0.05em; color: var(--vb-admin-text-tertiary); margin-bottom: 0.5rem; }
        .error-title { font-size: var(--vb-text-xl); font-weight: 600; margin-bottom: 0.5rem; }
        .error-desc { font-size: var(--vb-text-base); color: var(--vb-admin-text-secondary); margin-bottom: 2rem; }
        .error-btn {
            display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem;
            font-family: var(--vb-font-sans); font-size: var(--vb-text-sm); font-weight: 600;
            color: #fff; background: var(--vb-admin-accent); border: none; border-radius: var(--vb-radius-sm);
            text-decoration: none; transition: background 150ms;
        }
        .error-btn:hover { background: var(--vb-admin-accent-hover); }
        .error-btn svg { width: 16px; height: 16px; }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-icon">
            <!-- Lucide search -->
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <div class="error-code">404</div>
        <h1 class="error-title"><?= __('admin.errors.404_title') ?></h1>
        <p class="error-desc"><?= __('admin.errors.404_desc') ?></p>
        <a href="/admin" class="error-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            <?= __('admin.errors.403_action') ?>
        </a>
    </div>
</body>
</html>
