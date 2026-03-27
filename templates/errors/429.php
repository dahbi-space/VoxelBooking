<?php
/** 429 Too Many Requests — PRD §XV: clock icon, "Too many requests". */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>429 — VoxelBooking</title>
    <?php include dirname(__DIR__) . '/partials/admin-head.php'; ?>
    <style>
        body { font-family: var(--vb-font-sans); background: var(--vb-admin-bg-base); color: var(--vb-admin-text-primary); min-height: 100vh; display: flex; align-items: center; justify-content: center; -webkit-font-smoothing: antialiased; }
        .error-page { text-align: center; max-width: 400px; padding: 2rem; }
        .error-icon { color: var(--vb-admin-warning); margin-bottom: 1.5rem; }
        .error-code { font-size: 3rem; font-weight: 700; letter-spacing: -0.05em; color: var(--vb-admin-text-tertiary); margin-bottom: 0.5rem; }
        .error-title { font-size: var(--vb-text-xl); font-weight: 600; margin-bottom: 0.5rem; }
        .error-desc { font-size: var(--vb-text-base); color: var(--vb-admin-text-secondary); margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-icon">
            <!-- Lucide clock -->
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="error-code">429</div>
        <h1 class="error-title">Too many requests</h1>
        <p class="error-desc">Please wait a moment and try again.</p>
    </div>
</body>
</html>
