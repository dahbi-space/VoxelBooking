<?php
/**
 * Booking detail view.
 *
 * Two-column layout: left = booking info, right = status change.
 *
 * Variables: $user, $version, $csrfToken, $booking, $backUrl, $pageTitle, $activePage
 */
$activePage = 'bookings';

$statusClass = match ($booking['status']) {
    'confirmed'   => 'vb-badge-success',
    'pending'     => 'vb-badge-warning',
    'cancelled'   => 'vb-badge-error',
    'completed'   => 'vb-badge-default',
    'no_show'     => 'vb-badge-error',
    'rescheduled' => 'vb-badge-warning',
    default       => 'vb-badge-default',
};

ob_start();
?>

<div class="vb-page-header">
    <div>
        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="vb-back-link">
            <i data-lucide="chevron-left"></i>
            <?= __('admin.bookings.back_to_list') ?>
        </a>
        <h2 class="vb-page-title"><?= __('admin.bookings.detail_title') ?></h2>
    </div>
</div>

<?php if ($flash ?? null): ?>
    <div class="vb-alert vb-alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?php if ($flash['type'] === 'success'): ?>
            <i data-lucide="check"></i>
        <?php else: ?>
            <i data-lucide="alert-circle"></i>
        <?php endif; ?>
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="vb-grid vb-grid-2 vb-fade-in-up">
    <!-- Booking Info -->
    <div class="vb-card">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.bookings.detail_title') ?></div>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.customer') ?></span>
            <span class="vb-info-value">
                <div class="vb-cell-name"><?= htmlspecialchars($booking['customer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="vb-cell-detail"><?= htmlspecialchars($booking['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.service') ?></span>
            <span class="vb-info-value"><?= htmlspecialchars($booking['service_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.date_time') ?></span>
            <span class="vb-info-value">
                <?= date('l, M j, Y', strtotime($booking['start_datetime'])) ?><br>
                <span class="vb-text-muted">
                    <?= date('H:i', strtotime($booking['start_datetime'])) ?> – <?= date('H:i', strtotime($booking['end_datetime'])) ?>
                </span>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.status') ?></span>
            <span class="vb-info-value">
                <span class="vb-badge <?= $statusClass ?>"><?= __('admin.bookings.status_' . $booking['status']) ?></span>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.tenant') ?></span>
            <span class="vb-info-value"><?= htmlspecialchars($booking['tenant_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.source') ?></span>
            <span class="vb-info-value">
                <span class="vb-badge vb-badge-default"><?= htmlspecialchars(ucfirst($booking['source'] ?? 'widget'), ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.created') ?></span>
            <span class="vb-info-value"><?= htmlspecialchars($booking['created_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php if (!empty($booking['notes'])): ?>
        <div class="vb-info-row">
            <span class="vb-info-label">Notes</span>
            <span class="vb-info-value"><?= nl2br(htmlspecialchars($booking['notes'], ENT_QUOTES, 'UTF-8')) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status Change -->
    <div class="vb-card">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.bookings.change_status') ?></div>
        </div>
        <form method="POST" action="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($booking['id'], ENT_QUOTES, 'UTF-8') ?>/status">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="vb-form-group">
                <label for="booking_status" class="vb-label"><?= __('admin.bookings.status') ?></label>
                <select id="booking_status" name="status" class="vb-select">
                    <?php foreach (['pending','confirmed','cancelled','completed','no_show','rescheduled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $booking['status'] === $s ? 'selected' : '' ?>>
                            <?= __('admin.bookings.status_' . $s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="vb-form-actions">
                <button type="submit" class="vb-btn vb-btn-primary">
                    <i data-lucide="check"></i>
                    <?= __('admin.bookings.change_status') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
