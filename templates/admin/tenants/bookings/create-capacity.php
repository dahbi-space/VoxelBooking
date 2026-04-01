<?php
/**
 * Manual capacity booking creation form — tenant-scoped.
 *
 * Variables: $tenant, $tenantId, $slots, $csrfToken, $flash, $old
 */
$tenant   = $tenant ?? [];
$tenantId = $tenantId ?? '';
$slots    = $slots ?? [];
$old      = $old ?? [];
$baseUrl  = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');

$dayNames = [];
for ($i = 0; $i < 7; $i++) {
    $dayNames[$i] = __('admin.capacity_slots.day_' . $i);
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
            <!-- Time Slot -->
            <div class="vb-form-group">
                <label for="create_slot_id" class="vb-label"><?= __('admin.bookings.label_slot') ?> <span class="vb-required">*</span></label>
                <select id="create_slot_id" name="slot_id" class="vb-select" required>
                    <option value=""><?= __('admin.bookings.placeholder_select_slot') ?></option>
                    <?php foreach ($slots as $s): ?>
                        <option value="<?= htmlspecialchars($s['id'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($old['slot_id'] ?? '') === $s['id'] ? 'selected' : '' ?>>
                            <?= $dayNames[(int) $s['day_of_week']] ?> <?= substr($s['start_time'], 0, 5) ?>–<?= substr($s['end_time'], 0, 5) ?>
                            <?php if ($s['label']): ?>(<?= htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8') ?>)<?php endif; ?>
                            · <?= str_replace(':count', (string) (int) $s['max_capacity'], __('admin.capacity_slots.capacity_max_seats')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Party Size -->
            <div class="vb-form-group">
                <label for="create_party_size" class="vb-label"><?= __('admin.bookings.label_party_size') ?> <span class="vb-required">*</span></label>
                <input type="number" id="create_party_size" name="party_size" class="vb-input" required
                       min="1" value="<?= htmlspecialchars($old['party_size'] ?? '2', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="vb-form-row vb-form-row--2col">
            <!-- Date -->
            <div class="vb-form-group">
                <label for="create_date" class="vb-label"><?= __('admin.bookings.label_date') ?> <span class="vb-required">*</span></label>
                <input type="date" id="create_date" name="date" class="vb-input" required
                       value="<?= htmlspecialchars($old['date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="vb-form-group">
                <!-- Empty column for balance -->
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
