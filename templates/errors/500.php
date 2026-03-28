<?php
/** 500 Internal Server Error — PRD §XV: alert-triangle icon, standalone. */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>500 — VoxelBooking</title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <style>
        body { font-family: var(--vb-font-sans); background: var(--vb-admin-bg-base); color: var(--vb-admin-text-primary); min-height: 100vh; display: flex; align-items: center; justify-content: center; -webkit-font-smoothing: antialiased; }
        .error-page { text-align: center; max-width: 400px; padding: 2rem; }
        .error-icon { color: var(--vb-admin-error); margin-bottom: 1.5rem; }
        .error-code { font-size: 3rem; font-weight: 700; letter-spacing: -0.05em; color: var(--vb-admin-text-tertiary); margin-bottom: 0.5rem; }
        .error-title { font-size: var(--vb-text-xl); font-weight: 600; margin-bottom: 0.5rem; }
        .error-desc { font-size: var(--vb-text-base); color: var(--vb-admin-text-secondary); margin-bottom: 2rem; }
        .error-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-family: var(--vb-font-sans); font-size: var(--vb-text-sm); font-weight: 600; color: #fff; background: var(--vb-admin-accent); border: none; border-radius: var(--vb-radius-sm); text-decoration: none; transition: background 150ms; cursor: pointer; }
        .error-btn:hover { background: var(--vb-admin-accent-hover); }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-icon">
            <!-- Lucide alert-triangle -->
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        </div>
        <div class="error-code">500</div>
        <h1 class="error-title"><?= __('admin.errors.500_title') ?></h1>
        <p class="error-desc"><?= __('admin.errors.500_desc') ?></p>
        <button class="error-btn" id="retry-btn"><?= __('admin.errors.500_action') ?></button>
    </div>
    <script>
    (function() {
        var btn = document.getElementById('retry-btn');
        if (btn) { btn.addEventListener('click', function() { location.reload(); }); }
    })();
    </script>
</body>
</html>
