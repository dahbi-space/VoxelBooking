<?php
/**
 * Operator dashboard — Visual Design §16: Dashboard Specification.
 *
 * Three rhythm bands: metrics (top), activity/empty (middle), quick actions (bottom).
 * Empty state: horizontal layout with CTA and 3-step micro-guide.
 * Staggered card entrance animation.
 *
 * Variables: $user, $version
 */
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$csrfToken = \App\Middleware\CsrfMiddleware::generateToken();

ob_start();
?>

<!-- Metric Band -->
<div class="vb-grid vb-grid-4" style="margin-bottom: 1.5rem;">
    <div class="vb-metric vb-fade-in-up stagger-1">
        <div class="vb-metric-label">Active Tenants</div>
        <div class="vb-metric-value">0</div>
        <div class="vb-metric-trend is-flat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            No change
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-2">
        <div class="vb-metric-label">Bookings Today</div>
        <div class="vb-metric-value">0</div>
        <div class="vb-metric-trend is-flat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Awaiting first booking
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-3">
        <div class="vb-metric-label">This Week</div>
        <div class="vb-metric-value">0</div>
        <div class="vb-metric-trend is-flat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            No data yet
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-4">
        <div class="vb-metric-label">Upcoming (24h)</div>
        <div class="vb-metric-value">0</div>
        <div class="vb-metric-trend is-flat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            No upcoming
        </div>
    </div>
</div>

<!-- Activity Band / Empty State -->
<div class="vb-empty vb-fade-in-up stagger-5">
    <svg class="vb-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
        <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/>
        <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/>
        <path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
    </svg>
    <div>
        <div class="vb-empty-title">Welcome to VoxelBooking</div>
        <div class="vb-empty-desc">
            Create your first tenant to start managing bookings. Each tenant represents a business — a salon, restaurant, clinic, or any service provider you manage.
        </div>
        <button class="vb-btn vb-btn-primary vb-btn-lg" onclick="window.location.href='#'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create your first tenant
        </button>
        <div class="vb-empty-steps">
            <div class="vb-empty-step">
                <span class="vb-empty-step-num">1</span>
                Create tenant
            </div>
            <svg class="vb-empty-step-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            <div class="vb-empty-step">
                <span class="vb-empty-step-num">2</span>
                Configure services
            </div>
            <svg class="vb-empty-step-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            <div class="vb-empty-step">
                <span class="vb-empty-step-num">3</span>
                Share booking page
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/admin/layout.php';
