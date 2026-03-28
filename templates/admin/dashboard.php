<?php
/**
 * Operator dashboard — Visual Design §16: Dashboard Specification.
 *
 * Three rhythm bands:
 *   1. Greeting (personalized hero, time-of-day greeting)
 *   2. Metrics (4 metric cards with real data)
 *   3. Activity / Empty (onboarding CTA pointing to tenant creation)
 *
 * Icons: Lucide via data-lucide (rendered by admin/app.js).
 * Styles: admin.css (Tailwind 4) — .vb-greeting, .vb-metrics, .vb-empty.
 *
 * Variables: $user, $version, $activeTenants, $tenantCounts, $todayBookings, $weekBookings, $upcoming24h
 */
$pageTitle = __('admin.dashboard.title');
$activePage = 'dashboard';
$csrfToken = \App\Middleware\CsrfMiddleware::generateToken();

// Time-of-day greeting
$hour = (int) date('H');
if ($hour < 12) {
    $greeting = __('admin.dashboard.good_morning');
} elseif ($hour < 18) {
    $greeting = __('admin.dashboard.good_afternoon');
} else {
    $greeting = __('admin.dashboard.good_evening');
}
$userName = htmlspecialchars($user['name'] ?? __('admin.layout.operator'), ENT_QUOTES, 'UTF-8');

ob_start();
?>

<!-- Greeting Band -->
<div class="vb-greeting vb-fade-in-up stagger-1">
    <div class="vb-greeting-title"><?= $greeting ?>, <?= $userName ?></div>
    <div class="vb-greeting-subtitle"><?= __('admin.dashboard.greeting_subtitle', ['app_name' => app_name()]) ?></div>
</div>

<!-- Metric Band -->
<div class="vb-metrics">
    <div class="vb-metric vb-fade-in-up stagger-1">
        <div class="vb-metric-accent"></div>
        <div class="vb-metric-label"><?= __('admin.dashboard.active_tenants') ?></div>
        <div class="vb-metric-value"><?= (int) ($activeTenants ?? 0) ?></div>
        <div class="vb-metric-trend">
            <?php if (($activeTenants ?? 0) > 0): ?>
                <i data-lucide="trending-up"></i>
                <?= (int) $activeTenants ?> <?= __('admin.tenants.status_active') ?>
            <?php else: ?>
                <i data-lucide="minus"></i>
                <?= __('admin.dashboard.no_change') ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-2">
        <div class="vb-metric-label"><?= __('admin.dashboard.bookings_today') ?></div>
        <div class="vb-metric-value"><?= (int) ($todayBookings ?? 0) ?></div>
        <div class="vb-metric-trend">
            <?php if (($todayBookings ?? 0) > 0): ?>
                <i data-lucide="trending-up"></i>
            <?php else: ?>
                <i data-lucide="minus"></i>
            <?php endif; ?>
            <?= ($todayBookings ?? 0) > 0 ? (int) $todayBookings . ' today' : __('admin.dashboard.awaiting_first') ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-3">
        <div class="vb-metric-label"><?= __('admin.dashboard.this_week') ?></div>
        <div class="vb-metric-value"><?= (int) ($weekBookings ?? 0) ?></div>
        <div class="vb-metric-trend">
            <?php if (($weekBookings ?? 0) > 0): ?>
                <i data-lucide="trending-up"></i>
            <?php else: ?>
                <i data-lucide="minus"></i>
            <?php endif; ?>
            <?= ($weekBookings ?? 0) > 0 ? (int) $weekBookings . ' this week' : __('admin.dashboard.no_data_yet') ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-4">
        <div class="vb-metric-label"><?= __('admin.dashboard.upcoming_24h') ?></div>
        <div class="vb-metric-value"><?= (int) ($upcoming24h ?? 0) ?></div>
        <div class="vb-metric-trend">
            <?php if (($upcoming24h ?? 0) > 0): ?>
                <i data-lucide="clock"></i>
            <?php else: ?>
                <i data-lucide="minus"></i>
            <?php endif; ?>
            <?= ($upcoming24h ?? 0) > 0 ? (int) $upcoming24h . ' confirmed' : __('admin.dashboard.no_upcoming') ?>
        </div>
    </div>
</div>

<!-- Activity Band / Empty State -->
<?php if (($tenantCounts['total'] ?? 0) === 0): ?>
<div class="vb-empty vb-fade-in-up stagger-5">
    <i data-lucide="building-2" class="vb-empty-icon"></i>
    <div>
        <div class="vb-empty-title"><?= __('admin.dashboard.welcome_title', ['app_name' => app_name()]) ?></div>
        <div class="vb-empty-desc">
            <?= __('admin.dashboard.welcome_desc') ?>
        </div>
        <a href="/admin/tenants/create" class="vb-btn vb-btn-primary vb-btn-lg">
            <i data-lucide="plus"></i>
            <?= __('admin.dashboard.create_first_tenant') ?>
        </a>
        <div class="vb-empty-steps">
            <div class="vb-empty-step">
                <span class="vb-empty-step-num">1</span>
                <?= __('admin.dashboard.step_create') ?>
            </div>
            <i data-lucide="chevron-right" class="vb-empty-step-arrow"></i>
            <div class="vb-empty-step">
                <span class="vb-empty-step-num">2</span>
                <?= __('admin.dashboard.step_configure') ?>
            </div>
            <i data-lucide="chevron-right" class="vb-empty-step-arrow"></i>
            <div class="vb-empty-step">
                <span class="vb-empty-step-num">3</span>
                <?= __('admin.dashboard.step_share') ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="vb-card vb-fade-in-up stagger-5">
    <div class="vb-card-header">
        <div class="vb-card-title"><?= __('admin.common.actions') ?></div>
    </div>
    <div class="vb-action-group" style="padding: 1rem; gap: 0.75rem;">
        <a href="/admin/tenants/create" class="vb-btn vb-btn-primary">
            <i data-lucide="plus"></i>
            <?= __('admin.tenants.create') ?>
        </a>
        <a href="/admin/tenants" class="vb-btn vb-btn-ghost">
            <i data-lucide="building-2"></i>
            <?= __('admin.tenants.title') ?>
        </a>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/admin/layout.php';
