<?php
/**
 * Customer detail — premium contact workspace.
 *
 * Architecture:
 *   Back-link (slim breadcrumb, no duplicate title)
 *   Contact hero (.vb-contact-hero):
 *     Left:  avatar + name + meta (email, phone)
 *     Right: inline metrics (bookings, last visit, joined)
 *   Notes card (if present)
 *   Booking history section + table
 *
 * The name appears once — inside the hero. The page header is reduced to
 * a back-link only, eliminating the identity duplication.
 *
 * Variables: $tenant, $customer, $bookings, $liveBookingCount, $liveLastBookingAt, $tenantId, $csrfToken
 */
$tenant = $tenant ?? [];
$customer = $customer ?? [];
$bookings = $bookings ?? [];
$liveBookingCount = $liveBookingCount ?? 0;
$liveLastBookingAt = $liveLastBookingAt ?? null;
$tenantId = $tenantId ?? '';
$baseUrl = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');

ob_start();
?>

<!-- Back-link only — no duplicate title -->
<div class="vb-page-header vb-page-header--slim">
    <a href="<?= $baseUrl ?>/customers" class="vb-back-link">
        <i data-lucide="chevron-left"></i>
        <?= __('admin.customers.page_title') ?>
    </a>
</div>

<!-- Contact hero: identity + metrics in one unified surface -->
<div class="vb-contact-hero vb-animate-in">
    <div class="vb-contact-hero-identity">
        <span class="vb-avatar vb-avatar-xl"><?= mb_strtoupper(mb_substr($customer['name'], 0, 1)) ?></span>
        <div class="vb-contact-hero-info">
            <h2 class="vb-contact-hero-name"><?= htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="vb-profile-meta-row">
                <span class="vb-profile-meta-item">
                    <i data-lucide="mail"></i>
                    <?= htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($customer['phone']): ?>
                <span class="vb-profile-meta-item">
                    <i data-lucide="phone"></i>
                    <?= htmlspecialchars($customer['phone'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="vb-contact-hero-metrics">
        <div class="vb-contact-metric">
            <span class="vb-contact-metric-value vb-contact-metric-value--number"><?= $liveBookingCount ?></span>
            <span class="vb-contact-metric-label"><?= __('admin.customers.stat_total_bookings') ?></span>
        </div>
        <div class="vb-contact-metric">
            <span class="vb-contact-metric-value vb-contact-metric-value--date">
                <?php if ($liveLastBookingAt): ?>
                    <?= htmlspecialchars(\App\Engine\Locale::dateLong(new DateTimeImmutable($liveLastBookingAt)), ENT_QUOTES, 'UTF-8') ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </span>
            <span class="vb-contact-metric-label"><?= __('admin.customers.stat_last_booking') ?></span>
        </div>
        <div class="vb-contact-metric">
            <span class="vb-contact-metric-value vb-contact-metric-value--date">
                <?= htmlspecialchars(\App\Engine\Locale::dateLong(new DateTimeImmutable($customer['created_at'])), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="vb-contact-metric-label"><?= __('admin.customers.stat_joined') ?></span>
        </div>
    </div>
</div>

<!-- Notes -->
<?php if ($customer['notes']): ?>
<div class="vb-card vb-mb-lg vb-animate-in">
    <div class="vb-card-header">
        <div class="vb-card-title-row">
            <i data-lucide="notebook-pen" class="vb-card-icon"></i>
            <div class="vb-card-title"><?= __('admin.customers.notes_title') ?></div>
        </div>
    </div>
    <div class="vb-card-body">
        <p class="vb-text-secondary vb-pre-wrap"><?= htmlspecialchars($customer['notes'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Booking history -->
<div class="vb-section-header">
    <h3 class="vb-section-title"><?= __('admin.customers.booking_history') ?></h3>
    <?php if (!empty($bookings)): ?>
        <span class="vb-badge vb-badge-neutral"><?= count($bookings) ?></span>
    <?php endif; ?>
</div>

<?php if (empty($bookings)): ?>
    <div class="vb-empty-state vb-empty-state--compact vb-animate-in">
        <i data-lucide="calendar-x" class="vb-empty-icon"></i>
        <h3><?= __('admin.customers.no_bookings_title') ?></h3>
        <p><?= __('admin.customers.no_bookings_desc') ?></p>
    </div>
<?php else: ?>
    <div class="vb-table-container vb-animate-in">
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
