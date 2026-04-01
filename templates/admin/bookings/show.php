<?php
/**
 * Booking detail view.
 *
 * Two-column layout: left = booking info, right = status change.
 * Below: event timeline from audit log.
 *
 * Variables: $user, $version, $csrfToken, $booking, $backUrl, $pageTitle,
 *            $activePage, $flash, $timeline
 */
$activePage = 'bookings';

// Timeline label helper — maps audit action keys to translated labels
if (!function_exists('bookingTimelineLabel')) {
    function bookingTimelineLabel(array $event): string {
        $details = !empty($event['details']) ? json_decode($event['details'], true) : [];

        // Map audit action keys to existing admin.audit.* translation keys
        $actionKey = 'admin.audit.action_' . str_replace('.', '_', $event['action']);
        $label = __($actionKey);

        // If translation returns the raw key (key not found), humanize the action
        if ($label === $actionKey) {
            $label = ucfirst(str_replace(['.', '_'], ' ', $event['action']));
        }

        // Append old→new for status changes, using translated status labels
        if ($event['action'] === 'booking.status_changed'
            && isset($details['old_status'], $details['new_status'])) {
            $oldLabel = __('admin.bookings.status_' . $details['old_status']);
            $newLabel = __('admin.bookings.status_' . $details['new_status']);
            $label .= ': ' . $oldLabel . ' → ' . $newLabel;
        }

        return $label;
    }
}

ob_start();
?>

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

<div class="vb-page-header">
    <div>
        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="vb-back-link">
            <i data-lucide="chevron-left"></i>
            <?= __('admin.bookings.back_to_list') ?>
        </a>
        <h2 class="vb-page-title"><?= __('admin.bookings.detail_title') ?></h2>
    </div>
</div>

<div class="vb-grid vb-grid-2 vb-fade-in-up">
    <!-- Booking Info -->
    <div class="vb-card">
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.customer') ?></span>
            <span class="vb-info-value">
                <?php if (!empty($booking['customer_id'])): ?>
                    <div class="vb-cell-primary">
                        <a href="/admin/tenants/<?= htmlspecialchars($booking['tenant_id'], ENT_QUOTES, 'UTF-8') ?>/customers/<?= htmlspecialchars($booking['customer_id'], ENT_QUOTES, 'UTF-8') ?>"
                           class="vb-link">
                            <?= htmlspecialchars($booking['customer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="vb-cell-primary"><?= htmlspecialchars($booking['customer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div class="vb-cell-secondary"><?= htmlspecialchars($booking['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.service') ?></span>
            <span class="vb-info-value">
                <?php
                $itemLabel = match ($booking['booking_pattern'] ?? 'timeslot') {
                    'resource' => $booking['resource_name'] ?? '—',
                    'event'    => $booking['event_name'] ?? '—',
                    'capacity' => __('admin.bookings.capacity_booking'),
                    default    => $booking['service_name'] ?? '—',
                };
                ?>
                <?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <?php if (!empty($booking['staff_name'])): ?>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.bookings.label_staff') ?></span>
            <span class="vb-info-value"><?= htmlspecialchars($booking['staff_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>
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
                <span class="vb-status vb-status-<?= htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= __('admin.bookings.status_' . $booking['status']) ?>
                </span>
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
            <span class="vb-info-label"><?= __('admin.bookings.label_notes') ?></span>
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
                    <?php foreach (['pending','confirmed','cancelled','completed','no_show','rescheduled','waitlisted'] as $s): ?>
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

<!-- Event Timeline -->
<?php if (!empty($timeline)): ?>
<div class="vb-card vb-fade-in-up stagger-3" style="margin-top: 1.5rem;">
    <div class="vb-card-header">
        <div class="vb-card-title"><?= __('admin.bookings.activity') ?></div>
    </div>
    <div class="vb-card-body">
        <div class="vb-timeline">
            <?php foreach ($timeline as $event): ?>
            <div class="vb-timeline-entry <?= $event['action'] === 'booking.created' ? 'is-created' : (str_contains($event['action'], 'status') ? 'is-status' : '') ?>">
                <div class="vb-timeline-action"><?= htmlspecialchars(bookingTimelineLabel($event), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="vb-timeline-meta">
                    <?= date('M j, Y H:i', strtotime($event['created_at'])) ?>
                    · <?= htmlspecialchars($event['actor_type'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
