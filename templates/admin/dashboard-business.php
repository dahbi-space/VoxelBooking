<?php
/**
 * Business user dashboard — tenant-scoped view.
 *
 * Variables: $user, $version, $csrfToken, $tenant, $todayBookings, $weekBookings,
 *            $statusCounts, $upcoming, $pageTitle, $activePage
 */
$activePage = 'dashboard';

$hour = (int) date('H');
if ($hour < 12) {
    $greeting = __('admin.dashboard.good_morning');
} elseif ($hour < 18) {
    $greeting = __('admin.dashboard.good_afternoon');
} else {
    $greeting = __('admin.dashboard.good_evening');
}
$userName = htmlspecialchars($user['name'] ?? __('admin.layout.operator'), ENT_QUOTES, 'UTF-8');
$tenantName = htmlspecialchars($tenant['name'] ?? '', ENT_QUOTES, 'UTF-8');

ob_start();
?>

<!-- Greeting Band -->
<div class="vb-greeting vb-fade-in-up stagger-1">
    <div class="vb-greeting-title"><?= $greeting ?>, <?= $userName ?></div>
    <div class="vb-greeting-subtitle"><?= __('admin.dashboard.business_subtitle', ['tenant_name' => $tenantName]) ?></div>
</div>

<!-- Metric Band -->
<div class="vb-metrics">
    <div class="vb-metric vb-fade-in-up stagger-1">
        <div class="vb-metric-accent"></div>
        <div class="vb-metric-label"><?= __('admin.dashboard.bookings_today') ?></div>
        <div class="vb-metric-value"><?= (int) $todayBookings ?></div>
        <div class="vb-metric-trend">
            <?php if ($todayBookings > 0): ?>
                <i data-lucide="trending-up"></i>
            <?php else: ?>
                <i data-lucide="minus"></i>
            <?php endif; ?>
            <?= $todayBookings > 0 ? $todayBookings . ' today' : __('admin.dashboard.awaiting_first') ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-2">
        <div class="vb-metric-label"><?= __('admin.dashboard.this_week') ?></div>
        <div class="vb-metric-value"><?= (int) $weekBookings ?></div>
        <div class="vb-metric-trend">
            <?php if ($weekBookings > 0): ?>
                <i data-lucide="trending-up"></i>
            <?php else: ?>
                <i data-lucide="minus"></i>
            <?php endif; ?>
            <?= $weekBookings > 0 ? $weekBookings . ' this week' : __('admin.dashboard.no_data_yet') ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-3">
        <div class="vb-metric-label"><?= __('admin.dashboard.confirmed') ?></div>
        <div class="vb-metric-value"><?= (int) ($statusCounts['confirmed'] ?? 0) ?></div>
        <div class="vb-metric-trend">
            <i data-lucide="check-circle"></i>
            <?= __('admin.bookings.status_confirmed') ?>
        </div>
    </div>
    <div class="vb-metric vb-fade-in-up stagger-4">
        <div class="vb-metric-label"><?= __('admin.dashboard.completed') ?></div>
        <div class="vb-metric-value"><?= (int) ($statusCounts['completed'] ?? 0) ?></div>
        <div class="vb-metric-trend">
            <i data-lucide="award"></i>
            <?= __('admin.bookings.status_completed') ?>
        </div>
    </div>
</div>

<!-- Next Up + Actions -->
<div class="vb-grid vb-grid-2">
    <!-- Next Up -->
    <div class="vb-card vb-fade-in-up stagger-5">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.dashboard.next_up') ?></div>
        </div>
        <?php if (empty($upcoming)): ?>
            <div class="vb-empty vb-empty-compact">
                <i data-lucide="calendar-check" class="vb-empty-icon vb-empty-icon-sm"></i>
                <div class="vb-empty-desc"><?= __('admin.dashboard.no_upcoming_bookings') ?></div>
            </div>
        <?php else: ?>
            <div class="vb-table-wrap">
                <table class="vb-table">
                    <tbody>
                        <?php foreach ($upcoming as $b): ?>
                        <tr>
                            <td>
                                <div class="vb-cell-name"><?= htmlspecialchars($b['customer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="vb-cell-detail"><?= htmlspecialchars($b['service_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="vb-text-right">
                                <div class="vb-cell-name"><?= date('M j', strtotime($b['start_datetime'])) ?></div>
                                <div class="vb-cell-detail"><?= date('H:i', strtotime($b['start_datetime'])) ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="vb-card vb-fade-in-up stagger-6">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.common.actions') ?></div>
        </div>
        <div class="vb-card-actions">
            <a href="/admin/tenants/<?= htmlspecialchars($tenant['id'], ENT_QUOTES, 'UTF-8') ?>/bookings"
               class="vb-btn vb-btn-primary vb-btn-block">
                <i data-lucide="calendar"></i>
                <?= __('admin.dashboard.view_all_bookings') ?>
            </a>
            <a href="/book/<?= htmlspecialchars($tenant['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               target="_blank" rel="noopener"
               class="vb-btn vb-btn-ghost vb-btn-block">
                <i data-lucide="external-link"></i>
                <?= __('admin.dashboard.view_booking_page') ?>
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/admin/layout.php';
