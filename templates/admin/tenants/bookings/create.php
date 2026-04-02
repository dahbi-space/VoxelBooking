<?php
/**
 * Manual booking creation form — tenant-scoped.
 *
 * Variables: $tenant, $tenantId, $services, $staff, $serviceStaffMap, $csrfToken, $flash, $old
 */
$tenant          = $tenant ?? [];
$tenantId        = $tenantId ?? '';
$services        = $services ?? [];
$staff           = $staff ?? [];
$serviceStaffMap = $serviceStaffMap ?? [];
$old             = $old ?? [];
$slug            = htmlspecialchars($tenant['slug'] ?? '', ENT_QUOTES, 'UTF-8');
$baseUrl         = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');

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
      class="vb-create-booking-form"
      x-data="bookingCreate"
      data-slug="<?= $slug ?>"
      data-locale="<?= htmlspecialchars(\App\Engine\Locale::getLocale(), ENT_QUOTES, 'UTF-8') ?>"
      data-staff-map="<?= htmlspecialchars(json_encode($serviceStaffMap, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
      data-all-staff="<?= htmlspecialchars(json_encode(array_map(fn($m) => [
          'id'    => $m['id'],
          'label' => $m['name'] . ($m['title'] ? ' — ' . $m['title'] : ''),
      ], $staff), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
      data-old="<?= htmlspecialchars(json_encode([
          'service_id' => $old['service_id'] ?? '',
          'staff_id'   => $old['staff_id'] ?? '',
          'date'       => $old['date'] ?? '',
          'time'       => $old['time'] ?? '',
      ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Booking details card -->
    <div class="vb-card vb-fade-in-up stagger-1 vb-mb-md">
        <div class="vb-card-title"><?= __('admin.bookings.date_time') ?></div>

        <div class="vb-form-row vb-form-row--2col">
            <!-- Service -->
            <div class="vb-form-group">
                <label for="create_service_id" class="vb-label"><?= __('admin.bookings.label_service') ?> <span class="vb-required">*</span></label>
                <select id="create_service_id" name="service_id" class="vb-select" required
                        x-model="serviceId" @change="onServiceChange()">
                    <option value=""><?= __('admin.bookings.placeholder_select_service') ?></option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= htmlspecialchars($s['id'], ENT_QUOTES, 'UTF-8') ?>"
                                data-duration="<?= (int) $s['duration_minutes'] ?>"
                                <?= ($old['service_id'] ?? '') === $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                            (<?= str_replace(':count', (string) (int) $s['duration_minutes'], __('admin.bookings.duration_unit')) ?><?php if ($s['price']): ?> · <?= htmlspecialchars($s['price_label'] ?? $s['price'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Staff (filtered by selected service via Alpine) -->
            <div class="vb-form-group">
                <label for="create_staff_id" class="vb-label"><?= __('admin.bookings.label_staff') ?></label>
                <select id="create_staff_id" name="staff_id" class="vb-select"
                        x-model="staffId" @change="onStaffChange()">
                    <option value=""><?= __('admin.bookings.placeholder_any_staff') ?></option>
                    <template x-for="m in filteredStaff" :key="m.id">
                        <option :value="m.id" x-text="m.label" :selected="m.id === staffId"></option>
                    </template>
                </select>
            </div>
        </div>

        <div class="vb-form-row vb-form-row--2col">
            <!-- Date -->
            <div class="vb-form-group">
                <label for="create_date" class="vb-label"><?= __('admin.bookings.label_date') ?> <span class="vb-required">*</span></label>
                <input type="date" id="create_date" name="date" class="vb-input" required
                       x-model="date" @change="onDateChange()"
                       value="<?= htmlspecialchars($old['date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <!-- Time slot -->
            <div class="vb-form-group">
                <label for="create_time" class="vb-label"><?= __('admin.bookings.label_time') ?> <span class="vb-required">*</span></label>
                <select id="create_time" name="time" class="vb-select" required
                        x-model="time" :disabled="loadingSlots || slots.length === 0">
                    <template x-if="!serviceId || !date">
                        <option value=""><?= __('admin.bookings.placeholder_select_time') ?></option>
                    </template>
                    <template x-if="serviceId && date && loadingSlots">
                        <option value=""><?= __('admin.bookings.placeholder_loading_slots') ?></option>
                    </template>
                    <template x-if="serviceId && date && !loadingSlots && slots.length === 0">
                        <option value=""><?= __('admin.bookings.placeholder_no_slots') ?></option>
                    </template>
                    <template x-if="serviceId && date && !loadingSlots && slots.length > 0">
                        <option value=""><?= __('admin.bookings.label_time') ?>…</option>
                    </template>
                    <template x-for="slot in slots" :key="slot.time">
                        <option :value="slot.time" x-text="slot.label"></option>
                    </template>
                </select>
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
