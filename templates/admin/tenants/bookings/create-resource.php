<?php
/**
 * Manual resource booking creation form — tenant-scoped.
 *
 * Variables: $tenant, $tenantId, $resources, $csrfToken, $flash, $old
 */
$tenant    = $tenant ?? [];
$tenantId  = $tenantId ?? '';
$resources = $resources ?? [];
$old       = $old ?? [];
$baseUrl   = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');

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
        <div class="vb-card-title"><?= __('admin.bookings.date_time') ?></div>

        <div class="vb-form-row vb-form-row--2col">
            <!-- Resource (room) -->
            <div class="vb-form-group">
                <label for="create_resource_id" class="vb-label"><?= __('admin.bookings.label_resource') ?> <span class="vb-required">*</span></label>
                <select id="create_resource_id" name="resource_id" class="vb-select" required>
                    <option value=""><?= __('admin.bookings.placeholder_select_resource') ?></option>
                    <?php foreach ($resources as $r): ?>
                        <option value="<?= htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($old['resource_id'] ?? '') === $r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
                            (<?= (int) $r['capacity'] ?> guests<?php if ($r['price_per_night']): ?> · <?= htmlspecialchars($r['price_per_night'], ENT_QUOTES, 'UTF-8') ?>/night<?php endif; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Guests -->
            <div class="vb-form-group">
                <label for="create_guest_count" class="vb-label"><?= __('admin.bookings.label_guests') ?> <span class="vb-required">*</span></label>
                <input type="number" id="create_guest_count" name="guest_count" class="vb-input" required
                       min="1" value="<?= htmlspecialchars($old['guest_count'] ?? '1', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="vb-form-row vb-form-row--2col">
            <!-- Check-in -->
            <div class="vb-form-group">
                <label for="create_check_in" class="vb-label"><?= __('admin.bookings.label_check_in') ?> <span class="vb-required">*</span></label>
                <input type="date" id="create_check_in" name="check_in" class="vb-input" required
                       value="<?= htmlspecialchars($old['check_in'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <!-- Check-out -->
            <div class="vb-form-group">
                <label for="create_check_out" class="vb-label"><?= __('admin.bookings.label_check_out') ?> <span class="vb-required">*</span></label>
                <input type="date" id="create_check_out" name="check_out" class="vb-input" required
                       value="<?= htmlspecialchars($old['check_out'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
    </div>

    <!-- Customer details card -->
    <div class="vb-card vb-fade-in-up stagger-2 vb-mb-md">
        <div class="vb-card-title"><?= __('admin.bookings.customer') ?></div>

        <div class="vb-form-row vb-form-row--2col">
            <div class="vb-form-group">
                <label for="create_customer_name" class="vb-label"><?= __('admin.bookings.label_customer_name') ?> <span class="vb-required">*</span></label>
                <input type="text" id="create_customer_name" name="customer_name" class="vb-input" required
                       value="<?= htmlspecialchars($old['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="off">
            </div>
            <div class="vb-form-group">
                <label for="create_customer_email" class="vb-label"><?= __('admin.bookings.label_customer_email') ?> <span class="vb-required">*</span></label>
                <input type="email" id="create_customer_email" name="customer_email" class="vb-input" required
                       value="<?= htmlspecialchars($old['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="off">
            </div>
        </div>

        <div class="vb-form-row vb-form-row--2col">
            <div class="vb-form-group">
                <label for="create_customer_phone" class="vb-label">
                    <?= __('admin.bookings.label_customer_phone') ?>
                    <?php if ((int) ($tenant['require_phone'] ?? 0) === 1): ?>
                        <span class="vb-required">*</span>
                    <?php endif; ?>
                </label>
                <input type="tel" id="create_customer_phone" name="customer_phone" class="vb-input"
                       value="<?= htmlspecialchars($old['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       <?php if ((int) ($tenant['require_phone'] ?? 0) === 1): ?>required<?php endif; ?>
                       autocomplete="off">
            </div>
            <div class="vb-form-group">
                <!-- Empty column for balance -->
            </div>
        </div>
    </div>

    <!-- Notes card -->
    <div class="vb-card vb-fade-in-up stagger-3 vb-mb-md">
        <div class="vb-card-title"><?= __('admin.bookings.label_notes') ?></div>
        <div class="vb-form-group">
            <textarea id="create_notes" name="notes" class="vb-textarea" rows="3"
                      placeholder="<?= __('admin.bookings.label_notes') ?>…"><?= htmlspecialchars($old['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>

    <!-- Submit -->
    <div class="vb-form-actions vb-fade-in-up stagger-4">
        <a href="<?= $baseUrl ?>/bookings" class="vb-btn vb-btn-ghost">
            <?= __('admin.common.cancel') ?>
        </a>
        <button type="submit" class="vb-btn vb-btn-primary">
            <i data-lucide="check" style="width: 16px; height: 16px;"></i>
            <?= __('admin.bookings.btn_create_booking') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
