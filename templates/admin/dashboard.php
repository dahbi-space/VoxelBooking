<?php
/**
 * Operator dashboard — empty state.
 *
 * Variables: $user, $version
 */
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$csrfToken = \App\Middleware\CsrfMiddleware::generateToken();

ob_start();
?>
<style>
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .metric-card {
        background: var(--vb-admin-bg-surface); border: 1px solid var(--vb-admin-border-subtle);
        border-radius: var(--vb-radius); padding: 1.25rem;
        box-shadow: var(--vb-admin-shadow-sm);
    }
    .metric-label { font-size: var(--vb-text-sm); color: var(--vb-admin-text-tertiary); margin-bottom: 0.25rem; }
    .metric-value { font-size: 1.5rem; font-weight: 500; letter-spacing: var(--vb-tracking-tight); }
    .empty-state {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 4rem 2rem; text-align: center;
    }
    .empty-state svg { width: 48px; height: 48px; color: var(--vb-admin-text-ghost); margin-bottom: 1rem; }
    .empty-state h2 { font-size: var(--vb-text-lg); font-weight: 600; margin-bottom: 0.5rem; }
    .empty-state p { font-size: var(--vb-text-base); color: var(--vb-admin-text-secondary); max-width: 400px; }
</style>

<div class="dashboard-grid">
    <div class="metric-card">
        <div class="metric-label">Active Tenants</div>
        <div class="metric-value">0</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Bookings Today</div>
        <div class="metric-value">0</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">This Week</div>
        <div class="metric-value">0</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Upcoming (24h)</div>
        <div class="metric-value">0</div>
    </div>
</div>

<div class="empty-state">
    <!-- Lucide building-2 -->
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
    <h2>No tenants yet</h2>
    <p>Create your first tenant to start managing bookings. Each tenant represents a business you manage.</p>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/admin/layout.php';
