<?php
/**
 * Operator dashboard — Visual Design §16: Dashboard Specification.
 *
 * Three rhythm bands: metrics (top), activity/empty (middle), quick actions (bottom).
 * Empty state: horizontal layout with CTA and 3-step micro-guide.
 * Staggered card entrance animation.
 *
 * Icons: Lucide via data-lucide (rendered by admin/app.js).
 * State: Alpine.js for interactive elements.
 *
 * Variables: $user, $version
 */
$pageTitle = __('admin.dashboard.title');
$activePage = 'dashboard';
$csrfToken = \App\Middleware\CsrfMiddleware::generateToken();

ob_start();
?>

<!-- Metric Band -->
<div class="vb-grid vb-grid-4" style="margin-bottom: 1.5rem;">
    <div class="vb-metric vb-fade-in-up stagger-1">
        <div class="vb-metric-label"><?= __('admin.dashboard.active_tenants') ?></div>
        <div class="vb-metric-value">0</div>
        <div class="vb-metric-trend is-flat">
            <i data-lucide="minus" class="w-3.5 h-3.5"></i>
            <?= __('admin.dashboard.no_change') ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-2">
        <div class="vb-metric-label"><?= __('admin.dashboard.bookings_today') ?></div>
        <div class="vb-metric-value">0</div>
        <div class="vb-metric-trend is-flat">
            <i data-lucide="minus" class="w-3.5 h-3.5"></i>
            <?= __('admin.dashboard.awaiting_first') ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-3">
        <div class="vb-metric-label"><?= __('admin.dashboard.this_week') ?></div>
        <div class="vb-metric-value">0</div>
        <div class="vb-metric-trend is-flat">
            <i data-lucide="minus" class="w-3.5 h-3.5"></i>
            <?= __('admin.dashboard.no_data_yet') ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-4">
        <div class="vb-metric-label"><?= __('admin.dashboard.upcoming_24h') ?></div>
        <div class="vb-metric-value">0</div>
        <div class="vb-metric-trend is-flat">
            <i data-lucide="minus" class="w-3.5 h-3.5"></i>
            <?= __('admin.dashboard.no_upcoming') ?>
        </div>
    </div>
</div>

<!-- Activity Band / Empty State -->
<div class="vb-empty vb-fade-in-up stagger-5">
    <i data-lucide="building-2" class="vb-empty-icon"></i>
    <div>
        <div class="vb-empty-title"><?= __('admin.dashboard.welcome_title', ['app_name' => app_name()]) ?></div>
        <div class="vb-empty-desc">
            <?= __('admin.dashboard.welcome_desc') ?>
        </div>
        <button class="vb-btn vb-btn-primary vb-btn-lg" onclick="window.location.href='#'">
            <i data-lucide="plus"></i>
            <?= __('admin.dashboard.create_first_tenant') ?>
        </button>
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

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/admin/layout.php';
