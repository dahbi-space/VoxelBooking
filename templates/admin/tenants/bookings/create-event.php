<?php
/**
 * Manual event booking creation form — tenant-scoped.
 *
 * Variables: $tenant, $tenantId, $events, $csrfToken, $flash, $old
 */
$tenant   = $tenant ?? [];
$tenantId = $tenantId ?? '';
$events   = $events ?? [];
$old      = $old ?? [];
$baseUrl  = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');

ob_start();
?>

<?php if ($flash ?? null): ?>
    <?php include __DIR__ . '/../../../partials/alert.php'; ?>
<?php endif; ?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title">
            <i data-lucide="plus-circle" class="vb-page-header-icon"></i>
            <?= __('admin.bookings.create_title') ?>
        </h2>
        <div class="vb-page-subtitle"><?= __('admin.bookings.create_subtitle') ?></div>
    </div>
    <a href="<?= $baseUrl ?>/bookings" class="vb-btn vb-btn-ghost vb-btn-sm">
        <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
        <?= __('admin.bookings.back_to_list') ?>
    </a>
</div>

<form method="POST"
      action="<?= $baseUrl ?>/bookings/create"
      class="vb-create-booking-form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Booking details card -->
    <div class="vb-card vb-fade-in-up stagger-1 vb-mb-md">
        <div class="vb-card-title"><?= __('admin.events.title') ?></div>

        <div class="vb-form-row vb-form-row--2col">
            <!-- Event -->
            <div class="vb-form-group">
                <label for="create_event_id" class="vb-label"><?= __('admin.events.name_label') ?> <span class="vb-required">*</span></label>
                <select id="create_event_id" name="event_id" class="vb-select" required>
                    <option value=""><?= __('booking.event.select_event') ?></option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= htmlspecialchars($event['id'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($old['event_id'] ?? '') === $event['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?>
                            — <?= date('M j, Y H:i', strtotime($event['start_datetime'])) ?>
                            (<?= (int) $event['max_participants'] ?> max)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Spot Count -->
            <div class="vb-form-group">
                <label for="create_spot_count" class="vb-label"><?= __('booking.event.spots_title') ?></label>
                <input type="number" id="create_spot_count" name="spot_count" class="vb-input" min="1"
                       value="<?= htmlspecialchars($old['spot_count'] ?? '1', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <!-- Date (for recurring events) -->
        <div class="vb-form-group">
            <label for="create_date" class="vb-label"><?= __('booking.event.date_label') ?></label>
            <input type="date" id="create_date" name="date" class="vb-input"
                   value="<?= htmlspecialchars($old['date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="vb-form-help"><?= __('admin.events.exception_dates_help') ?></div>
        </div>
    </div>

    <!-- Customer card -->
    <div class="vb-card vb-fade-in-up stagger-2 vb-mb-md">
        <div class="vb-card-title"><?= __('admin.bookings.customer') ?></div>

        <div class="vb-form-row vb-form-row--2col">
            <div class="vb-form-group">
                <label for="create_name" class="vb-label"><?= __('admin.bookings.label_name') ?> <span class="vb-required">*</span></label>
                <input type="text" id="create_name" name="customer_name" class="vb-input" required
                       value="<?= htmlspecialchars($old['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= __('admin.bookings.placeholder_name') ?>">
            </div>
            <div class="vb-form-group">
                <label for="create_email" class="vb-label"><?= __('admin.bookings.label_email') ?> <span class="vb-required">*</span></label>
                <input type="email" id="create_email" name="customer_email" class="vb-input" required
                       value="<?= htmlspecialchars($old['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= __('admin.bookings.placeholder_email') ?>">
            </div>
        </div>

        <div class="vb-form-row vb-form-row--2col">
            <div class="vb-form-group">
                <label for="create_phone" class="vb-label"><?= __('admin.bookings.label_phone') ?></label>
                <input type="tel" id="create_phone" name="customer_phone" class="vb-input"
                       value="<?= htmlspecialchars($old['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= __('admin.bookings.placeholder_phone') ?>">
            </div>
        </div>
    </div>

    <!-- Notes card -->
    <div class="vb-card vb-fade-in-up stagger-3 vb-mb-md">
        <div class="vb-card-title"><?= __('admin.bookings.label_notes') ?></div>
        <div class="vb-form-group">
            <textarea id="create_notes" name="notes" class="vb-textarea" rows="3"
                      placeholder="<?= __('admin.bookings.placeholder_notes') ?>"><?= htmlspecialchars($old['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>

    <div class="vb-form-actions">
        <button type="submit" class="vb-btn vb-btn-primary" id="btn-create-event-booking">
            <i data-lucide="check" style="width: 14px; height: 14px;"></i>
            <?= __('admin.bookings.create_title') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
