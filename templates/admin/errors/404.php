<?php
/**
 * 404 Not Found — rendered inside the admin shell.
 * Shows sidebar + topbar for authenticated users, standalone for guests.
 */
$activePage = '';
ob_start();
?>
<div style="display:flex;align-items:center;justify-content:center;min-height:60vh;text-align:center;">
    <div style="max-width:400px;padding:2rem;">
        <div style="color:var(--vb-admin-text-ghost);margin-bottom:1.5rem;">
            <!-- Lucide search -->
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <div style="font-size:3rem;font-weight:700;letter-spacing:-0.05em;color:var(--vb-admin-text-tertiary);margin-bottom:0.5rem;">404</div>
        <h2 style="font-size:var(--vb-text-xl);font-weight:600;margin-bottom:0.5rem;">Page not found</h2>
        <p style="font-size:var(--vb-text-base);color:var(--vb-admin-text-secondary);margin-bottom:2rem;">The page you're looking for doesn't exist or has been moved.</p>
        <a href="/admin" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1.25rem;font-family:var(--vb-font-sans);font-size:var(--vb-text-sm);font-weight:600;color:#fff;background:var(--vb-admin-accent);border:none;border-radius:var(--vb-radius-sm);text-decoration:none;transition:background 150ms;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Back to dashboard
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layout.php';
