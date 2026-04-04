<?php
/**
 * Customer detail — premium contact profile surface.
 *
 * Architecture:
 *   Identity header (avatar + name + email + phone)
 *   Metadata row (joined date, booking count, last visit)
 *   Notes card (if present)
 *   Booking history table (shared table system)
 *
 * Variables: $tenant, $customer, $bookings, $liveBookingCount, $liveLastBookingAt, $tenantId, $csrfToken
 */
$tenant = $tenant ?? [];
$customer = $customer ?? [];
$bookings = $bookings ?? [];
$liveBookingCount = $liveBookingCount ?? 0;
$liveLastBookingAt = $liveLastBookingAt ?? null;
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

<!-- Identity header -->
<div class="vb-profile-header vb-animate-in">
    <span class="vb-avatar vb-avatar-xl"><?= mb_strtoupper(mb_substr($customer['name'], 0, 1)) ?></span>
    <div class="vb-profile-header-info">
        <h2 class="vb-profile-name"><?= htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="vb-profile-meta-row">
            <span class="vb-profile-meta-item">
                <i data-lucide="mail" style="width: 13px; height: 13px;"></i>
                <?= htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php if ($customer['phone']): ?>
            <span class="vb-profile-meta-item">
                <i data-lucide="contact" style="width: 13px; height: 13px;"></i>
                <?= htmlspecialchars($customer['phone'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats strip -->
<div class="vb-profile-stats vb-animate-in">
    <div class="vb-profile-stat">
        <span class="vb-profile-stat-value"><?= $liveBookingCount ?></span>
        <span class="vb-profile-stat-label"><?= __('admin.customers.stat_total_bookings') ?></span>
    </div>
    <div class="vb-profile-stat-divider"></div>
    <div class="vb-profile-stat">
        <span class="vb-profile-stat-value vb-profile-stat-value--date">
            <?php if ($liveLastBookingAt): ?>
                <?= htmlspecialchars(\App\Engine\Locale::dateLong(new DateTimeImmutable($liveLastBookingAt)), ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
                —
            <?php endif; ?>
        </span>
        <span class="vb-profile-stat-label"><?= __('admin.customers.stat_last_booking') ?></span>
    </div>
    <div class="vb-profile-stat-divider"></div>
    <div class="vb-profile-stat">
        <span class="vb-profile-stat-value vb-profile-stat-value--date">
            <?= htmlspecialchars(\App\Engine\Locale::dateLong(new DateTimeImmutable($customer['created_at'])), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <span class="vb-profile-stat-label"><?= __('admin.customers.stat_joined') ?></span>
    </div>
</div>

<!-- Notes -->
<?php if ($customer['notes']): ?>
<div class="vb-card vb-animate-in vb-mb-lg">
    <div class="vb-card-header">
        <i data-lucide="sticky-note" style="width: 16px; height: 16px;"></i>
        <strong><?= __('admin.customers.notes_title') ?></strong>
    </div>
    <div class="vb-card-body">
        <p class="vb-text-secondary vb-pre-wrap"><?= htmlspecialchars($customer['notes'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Booking history -->
<div class="vb-section-header">
    <h3 class="vb-section-title"><?= __('admin.customers.booking_history') ?></h3>
</div>

<?php if (empty($bookings)): ?>
    <div class="vb-empty-state vb-empty-state--compact vb-animate-in">
        <i data-lucide="calendar-x" class="vb-empty-icon"></i>
        <h3><?= __('admin.customers.no_bookings_title') ?></h3>
        <p><?= __('admin.customers.no_bookings_desc') ?></p>
    </div>
<?php else: ?>
    <div class="vb-table-container">
        <div class="vb-table-wrap">
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
                    <?php
                    $startDt = new DateTimeImmutable($b['start_datetime']);
                    $endDt   = new DateTimeImmutable($b['end_datetime']);
                    ?>
                    <tr class="vb-fade-in-up stagger-<?= min($i + 1, 6) ?>">
                        <td>
                            <?= htmlspecialchars(\App\Engine\Locale::dateLong($startDt), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="vb-text-secondary">
                            <?= htmlspecialchars(\App\Engine\Locale::time($startDt), ENT_QUOTES, 'UTF-8') ?>
                            –
                            <?= htmlspecialchars(\App\Engine\Locale::time($endDt), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?php $histLabel = booking_display_label($b); ?>
                            <?php if ($histLabel !== '—'): ?>
                                <?= htmlspecialchars($histLabel, ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="vb-text-tertiary">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="vb-status vb-status-<?= htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= __('admin.bookings.status_' . $b['status']) ?>
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
