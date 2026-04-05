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
<div class="vb-card vb-animate-in vb-mb-lg">
    <div class="vb-selector-strip">
        <label class="vb-label" for="availability-staff-selector">
            <i data-lucide="users" class="vb-icon-sm"></i>
            <?= __('admin.availability.staff_selector_label') ?>
        </label>
        <select id="availability-staff-selector"
                class="vb-input"
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
              data-confirm="Reset this staff member's hours to tenant defaults?" data-confirm-text="Reset"
              class="vb-ml-auto">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="vb-btn vb-btn-ghost vb-btn-sm vb-btn-danger">
                <i data-lucide="rotate-ccw" class="vb-icon-sm"></i>
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

    <div class="vb-card overflow-hidden">
        <div class="flex flex-col">
            <template x-for="(daySlots, dayIndex) in days" :key="dayIndex">
            <div class="flex flex-col md:flex-row md:items-start gap-4 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors border-b border-[var(--vb-border-subtle)] last:border-0"
                 style="padding: 1.25rem 1.5rem;"
                 :class="daySlots.length === 0 ? 'opacity-60' : ''">
                 
                <!-- Day Label -->
                <div class="w-full md:w-56 flex items-center justify-between h-[36px]">
                    <div class="flex items-center gap-4">
                        <label class="relative flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   class="sr-only peer"
                                   :checked="daySlots.length > 0"
                                   @change="$el.checked ? addWindow(dayIndex) : daySlots.splice(0, daySlots.length)">
                            <div class="w-9 h-5 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                        <span class="font-medium text-[0.9rem] text-[var(--vb-text-primary)]" 
                              x-text="dayLabels[dayIndex]"></span>
                    </div>
                </div>

                <!-- Time Windows -->
                <div class="flex-1 flex flex-col gap-3">
                    <template x-if="daySlots.length === 0">
                        <div class="text-[0.9rem] text-[var(--vb-text-tertiary)] flex items-center h-[36px] font-medium">
                            <?= __('admin.availability.closed') ?>
                        </div>
                    </template>

                    <template x-for="(window, idx) in daySlots" :key="idx">
                        <div class="flex items-center gap-3 group mb-3 last:mb-0">
                            <input type="time"
                                   class="vb-input px-3 py-1.5 h-[36px] text-sm md:w-36 w-full max-w-[140px]"
                                   :name="'schedule[' + dayIndex + '][' + idx + '][start]'"
                                   x-model="window.start"
                                   required>
                            <span class="text-[var(--vb-text-tertiary)] text-sm font-medium">to</span>
                            <input type="time"
                                   class="vb-input px-3 py-1.5 h-[36px] text-sm md:w-36 w-full max-w-[140px]"
                                   :name="'schedule[' + dayIndex + '][' + idx + '][end]'"
                                   x-model="window.end"
                                   required>
                            <button type="button"
                                    class="text-[var(--vb-text-tertiary)] hover:text-[var(--vb-error)] p-1.5 rounded-md transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
                                    @click="removeWindow(dayIndex, idx)"
                                    title="<?= __('admin.availability.remove_window') ?>">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Actions -->
                <div class="md:w-16 flex justify-end h-[36px] items-center">
                    <button type="button"
                            class="text-[var(--vb-text-secondary)] hover:text-[var(--vb-text-primary)] p-1.5 rounded-md transition-colors"
                            x-show="daySlots.length > 0"
                            @click="addWindow(dayIndex)"
                            title="<?= __('admin.availability.add_window') ?>">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </button>
                </div>

            </div>
            </template>
        </div>
    </div>

    <div class="vb-form-actions mt-6">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-availability-btn">
            <i data-lucide="save" class="w-4 h-4"></i>
            <?= __('admin.availability.save') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
