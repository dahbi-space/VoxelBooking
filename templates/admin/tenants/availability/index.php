<?php
/**
 * Admin — Weekly Availability Grid Editor
 *
 * Shared between tenant-defaults and staff-override views.
 * Uses Alpine.js CSP-safe component (registered in admin/app.js).
 *
 * @var array  $tenant
 * @var array  $schedule       7-element array, each is list of ['start' => 'HH:MM', 'end' => 'HH:MM']
 * @var array  $staff          Active staff list for selector
 * @var array  $staffOverrides Staff IDs that have custom overrides
 * @var string|null $currentStaffId  Currently editing staff, null = tenant defaults
 * @var array|null  $currentStaff    Staff record if editing override
 * @var bool   $hasOverride    Whether current staff has their own rows (only set when currentStaffId)
 * @var string $tenantId
 * @var string $csrfToken
 * @var array|null $flash
 */
$tenant = $tenant ?? [];
$staff = $staff ?? [];
$tenantId = $tenantId ?? '';
$schedule = $schedule ?? array_fill(0, 7, []);

$dayLabels = [
    __('admin.availability.day_mon'),
    __('admin.availability.day_tue'),
    __('admin.availability.day_wed'),
    __('admin.availability.day_thu'),
    __('admin.availability.day_fri'),
    __('admin.availability.day_sat'),
    __('admin.availability.day_sun'),
];

$formAction = $currentStaffId
    ? "/admin/tenants/{$tenantId}/availability/staff/{$currentStaffId}"
    : "/admin/tenants/{$tenantId}/availability";

$subtitle = $currentStaffId
    ? str_replace(':name', htmlspecialchars($currentStaff['name'] ?? '', ENT_QUOTES, 'UTF-8'), __('admin.availability.staff_subtitle'))
    : __('admin.availability.subtitle');

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.availability.title') ?></h2>
        <p class="vb-page-subtitle"><?= $subtitle ?></p>
    </div>
</div>

<?php if ($flash): ?>
    <?php include __DIR__ . '/../../../partials/alert.php'; ?>
<?php endif; ?>

<!-- Staff selector -->
<?php if (!empty($staff)): ?>
<div class="vb-card vb-animate-in" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; padding: 0.875rem 1.25rem;">
        <label class="vb-label" for="availability-staff-selector" style="margin: 0; white-space: nowrap; font-size: 0.8125rem; color: var(--vb-text-secondary);">
            <i data-lucide="users" style="width: 14px; height: 14px; display: inline; vertical-align: -2px; margin-right: 0.25rem;"></i>
            <?= __('admin.availability.staff_selector_label') ?>
        </label>
        <select id="availability-staff-selector"
                class="vb-input"
                style="max-width: 280px; font-size: 0.8125rem;"
                onchange="if(this.value==='defaults'){location.href='/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/availability'}else{location.href='/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/availability/staff/'+this.value}">
            <option value="defaults" <?= !$currentStaffId ? 'selected' : '' ?>>
                <?= __('admin.availability.tenant_defaults') ?>
            </option>
            <?php foreach ($staff as $s): ?>
            <option value="<?= htmlspecialchars($s['id'], ENT_QUOTES, 'UTF-8') ?>"
                    <?= $currentStaffId === $s['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                <?php if ($s['title']): ?>(<?= htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8') ?>)<?php endif; ?>
                <?php if (in_array($s['id'], $staffOverrides ?? [], true)): ?> ★<?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>

        <?php if ($currentStaffId): ?>
        <span class="vb-badge <?= ($hasOverride ?? false) ? 'vb-badge-accent' : 'vb-badge-muted' ?>">
            <?= ($hasOverride ?? false) ? __('admin.availability.using_custom') : __('admin.availability.using_defaults') ?>
        </span>
        <?php endif; ?>

        <?php if ($currentStaffId && ($hasOverride ?? false)): ?>
        <form method="POST"
              action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/availability/staff/<?= htmlspecialchars($currentStaffId, ENT_QUOTES, 'UTF-8') ?>/reset"
              onsubmit="return confirm('Reset this staff member\'s hours to tenant defaults?')"
              style="margin-left: auto;">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="vb-btn vb-btn-ghost vb-btn-sm vb-btn-danger">
                <i data-lucide="rotate-ccw" style="width: 13px; height: 13px;"></i>
                <?= __('admin.availability.reset_to_defaults') ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Weekly grid form -->
<form method="POST"
      action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>"
      x-data="availabilityGrid"
      data-schedule="<?= htmlspecialchars(json_encode($schedule, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
      data-day-labels="<?= htmlspecialchars(json_encode($dayLabels, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
      class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="vb-availability-grid">
        <template x-for="(daySlots, dayIndex) in days" :key="dayIndex">
        <div class="vb-availability-day" :class="daySlots.length === 0 && 'is-closed'">
            <div class="vb-availability-day-header">
                <div class="vb-availability-day-label-group">
                    <h4 class="vb-availability-day-label" x-text="dayLabels[dayIndex] || ''"></h4>
                    <span class="vb-availability-day-count"
                          x-text="daySlots.length > 0 ? daySlots.length + (daySlots.length === 1 ? ' window' : ' windows') : ''"></span>
                </div>
                <button type="button"
                        class="vb-btn vb-btn-ghost vb-btn-xs"
                        @click="addWindow(dayIndex)"
                        :title="'<?= __('admin.availability.add_window') ?>'">
                    <i data-lucide="plus" style="width: 14px; height: 14px;"></i>
                </button>
            </div>

            <div class="vb-availability-windows">
                <template x-if="daySlots.length === 0">
                    <p class="vb-availability-closed"><?= __('admin.availability.closed') ?></p>
                </template>

                <template x-for="(window, idx) in daySlots" :key="idx">
                    <div class="vb-availability-window">
                        <div class="vb-availability-inputs">
                            <input type="time"
                                   class="vb-input vb-input-sm"
                                   :name="'schedule[' + dayIndex + '][' + idx + '][start]'"
                                   x-model="window.start"
                                   required>
                            <span class="vb-availability-separator">→</span>
                            <input type="time"
                                   class="vb-input vb-input-sm"
                                   :name="'schedule[' + dayIndex + '][' + idx + '][end]'"
                                   x-model="window.end"
                                   required>
                        </div>
                        <button type="button"
                                class="vb-btn vb-btn-ghost vb-btn-xs vb-btn-destructive"
                                @click="removeWindow(dayIndex, idx)"
                                :title="'<?= __('admin.availability.remove_window') ?>'">
                            <i data-lucide="minus" style="width: 12px; height: 12px;"></i>
                        </button>
                    </div>
                </template>
            </div>
        </div>
        </template>
    </div>

    <div class="vb-form-actions" style="margin-top: 1.5rem;">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-availability-btn">
            <i data-lucide="save" style="width: 16px; height: 16px;"></i>
            <?= __('admin.availability.save') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
