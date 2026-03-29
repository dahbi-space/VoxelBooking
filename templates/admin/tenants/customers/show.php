<?php
/**
 * Customer detail — booking history + profile.
 *
 * Variables: $tenant, $customer, $bookings, $tenantId, $csrfToken
 */
$tenant = $tenant ?? [];
$customer = $customer ?? [];
$bookings = $bookings ?? [];
$tenantId = $tenantId ?? '';

ob_start();
?>

<!-- Breadcrumb -->
<div class="vb-breadcrumb">
    <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/customers" class="vb-breadcrumb-link">
        <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
        <?= __('admin.customers.page_title') ?>
    </a>
</div>

<div class="vb-page-header">
    <div class="vb-customer-header-info">
        <span class="vb-avatar vb-avatar-lg"><?= mb_strtoupper(mb_substr($customer['name'], 0, 1)) ?></span>
        <div>
            <h2 class="vb-page-title"><?= htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="vb-page-subtitle"><?= htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
</div>

<!-- Customer info cards -->
<div class="vb-stats-grid vb-stats-grid-4">
    <div class="vb-stat-card vb-animate-in">
        <div class="vb-stat-label"><?= __('admin.customers.stat_total_bookings') ?></div>
        <div class="vb-stat-value"><?= (int) $customer['booking_count'] ?></div>
    </div>
    <div class="vb-stat-card vb-animate-in">
        <div class="vb-stat-label"><?= __('admin.customers.stat_last_booking') ?></div>
        <div class="vb-stat-value vb-stat-value-sm">
            <?php if ($customer['last_booking_at']): ?>
                <?= htmlspecialchars(date('M j, Y', strtotime($customer['last_booking_at'])), ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
                —
            <?php endif; ?>
        </div>
    </div>
    <div class="vb-stat-card vb-animate-in">
        <div class="vb-stat-label"><?= __('admin.customers.stat_phone') ?></div>
        <div class="vb-stat-value vb-stat-value-sm">
            <?php if ($customer['phone']): ?>
                <?= htmlspecialchars($customer['phone'], ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
                <span class="vb-text-tertiary">—</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="vb-stat-card vb-animate-in">
        <div class="vb-stat-label"><?= __('admin.customers.stat_joined') ?></div>
        <div class="vb-stat-value vb-stat-value-sm">
            <?= htmlspecialchars(date('M j, Y', strtotime($customer['created_at'])), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
</div>

<?php if ($customer['notes']): ?>
<div class="vb-card vb-animate-in" style="margin-bottom: var(--space-5);">
    <div class="vb-card-header">
        <i data-lucide="sticky-note" style="width: 16px; height: 16px;"></i>
        <strong><?= __('admin.customers.notes_title') ?></strong>
    </div>
    <div class="vb-card-body">
        <p class="vb-text-secondary" style="white-space: pre-wrap;"><?= htmlspecialchars($customer['notes'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Booking history -->
<div class="vb-section-header">
    <h3 class="vb-section-title"><?= __('admin.customers.booking_history') ?></h3>
</div>

<?php if (empty($bookings)): ?>
    <div class="vb-empty-state vb-animate-in" style="padding: var(--space-8) var(--space-4);">
        <i data-lucide="calendar-x" class="vb-empty-icon" style="width: 32px; height: 32px;"></i>
        <h3><?= __('admin.customers.no_bookings_title') ?></h3>
        <p><?= __('admin.customers.no_bookings_desc') ?></p>
    </div>
<?php else: ?>
    <div class="vb-card">
        <div class="vb-table-wrapper">
            <table class="vb-table" id="customer-bookings-table">
                <thead>
                    <tr>
                        <th><?= __('admin.customers.bh_date') ?></th>
                        <th><?= __('admin.customers.bh_time') ?></th>
                        <th><?= __('admin.customers.bh_service') ?></th>
                        <th><?= __('admin.customers.bh_status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $i => $b): ?>
                    <tr class="vb-fade-in-up stagger-<?= min($i + 1, 6) ?>">
                        <td>
                            <?= htmlspecialchars(date('M j, Y', strtotime($b['start_datetime'])), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="vb-text-secondary">
                            <?= htmlspecialchars(date('H:i', strtotime($b['start_datetime'])), ENT_QUOTES, 'UTF-8') ?>
                            –
                            <?= htmlspecialchars(date('H:i', strtotime($b['end_datetime'])), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?php if ($b['service_name']): ?>
                                <?= htmlspecialchars($b['service_name'], ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="vb-text-tertiary">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = match ($b['status']) {
                                'confirmed'   => 'vb-badge-success',
                                'completed'   => 'vb-badge-primary',
                                'pending'     => 'vb-badge-warning',
                                'cancelled'   => 'vb-badge-danger',
                                'no_show'     => 'vb-badge-danger',
                                'rescheduled' => 'vb-badge-neutral',
                                default       => 'vb-badge-neutral',
                            };
                            ?>
                            <span class="vb-badge <?= $statusClass ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $b['status'])), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
