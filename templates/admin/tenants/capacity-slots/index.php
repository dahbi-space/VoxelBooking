<?php
/**
 * Capacity slots management — tenant-scoped, capacity-pattern only.
 *
 * Weekly grid of time windows with max capacity and party size.
 *
 * Variables: $tenant, $slots, $tenantId, $dayNames, $csrfToken, $flash
 */
$tenant = $tenant ?? [];
$slots = $slots ?? [];
$tenantId = $tenantId ?? '';
$dayNames = [];
for ($i = 0; $i < 7; $i++) {
    $dayNames[$i] = __('admin.capacity_slots.day_' . $i);
}

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.capacity_slots.title') ?></h2>
        <p class="vb-page-subtitle"><?= __('admin.capacity_slots.subtitle') ?></p>
    </div>
</div>

<?php if ($flash): ?>
    <?php include __DIR__ . '/../../../partials/alert.php'; ?>
<?php endif; ?>

<!-- Add Slot Form -->
<div class="vb-card" style="margin-bottom: 1.5rem;">
    <div class="vb-card-header">
        <h3 class="vb-card-title"><?= __('admin.capacity_slots.add_slot') ?></h3>
    </div>
    <div class="vb-card-body">
        <form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/capacity-slots" id="add-slot-form">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="vb-form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; align-items: end;">
                <div class="vb-form-group">
                    <label class="vb-label" for="slot-day"><?= __('admin.capacity_slots.label_day') ?></label>
                    <select name="day_of_week" id="slot-day" class="vb-select" required>
                        <?php for ($d = 0; $d < 7; $d++): ?>
                            <option value="<?= $d ?>"><?= $dayNames[$d] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="vb-form-group">
                    <label class="vb-label" for="slot-start"><?= __('admin.capacity_slots.label_start_time') ?></label>
                    <input type="time" name="start_time" id="slot-start" class="vb-input" required value="18:00">
                </div>
                <div class="vb-form-group">
                    <label class="vb-label" for="slot-end"><?= __('admin.capacity_slots.label_end_time') ?></label>
                    <input type="time" name="end_time" id="slot-end" class="vb-input" required value="20:00">
                </div>
                <div class="vb-form-group">
                    <label class="vb-label" for="slot-capacity"><?= __('admin.capacity_slots.label_capacity') ?></label>
                    <input type="number" name="max_capacity" id="slot-capacity" class="vb-input" required min="1" value="20">
                </div>
                <div class="vb-form-group">
                    <label class="vb-label" for="slot-min-party"><?= __('admin.capacity_slots.label_min_party_size') ?></label>
                    <input type="number" name="min_party_size" id="slot-min-party" class="vb-input" required min="1" value="1">
                </div>
                <div class="vb-form-group">
                    <label class="vb-label" for="slot-party"><?= __('admin.capacity_slots.label_party_size') ?></label>
                    <input type="number" name="max_party_size" id="slot-party" class="vb-input" required min="1" value="8">
                </div>
                <div class="vb-form-group">
                    <label class="vb-label" for="slot-label"><?= __('admin.capacity_slots.label_label') ?></label>
                    <input type="text" name="label" id="slot-label" class="vb-input" placeholder="<?= __('admin.capacity_slots.placeholder_label') ?>">
                </div>
                <div class="vb-form-group">
                    <button type="submit" class="vb-btn vb-btn-primary" id="add-slot-btn">
                        <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                        <?= __('admin.capacity_slots.add_slot') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Slots Table -->
<?php if (empty($slots)): ?>
    <div class="vb-empty-state vb-animate-in">
        <i data-lucide="grid-3x3" class="vb-empty-icon"></i>
        <h3><?= __('admin.capacity_slots.empty_title') ?></h3>
        <p><?= __('admin.capacity_slots.empty_description') ?></p>
    </div>
<?php else: ?>
    <div class="vb-card">
        <div class="vb-table-wrapper">
            <table class="vb-table" id="capacity-slots-table">
                <thead>
                    <tr>
                        <th><?= __('admin.capacity_slots.label_day') ?></th>
                        <th><?= __('admin.capacity_slots.label_start_time') ?></th>
                        <th><?= __('admin.capacity_slots.label_end_time') ?></th>
                        <th class="vb-text-center"><?= __('admin.capacity_slots.label_capacity') ?></th>
                        <th class="vb-text-center"><?= __('admin.capacity_slots.label_min_party_size') ?></th>
                        <th class="vb-text-center"><?= __('admin.capacity_slots.label_party_size') ?></th>
                        <th><?= __('admin.capacity_slots.label_label') ?></th>
                        <th><?= __('admin.capacity_slots.label_status') ?></th>
                        <th class="vb-text-right"><?= __('admin.capacity_slots.label_actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slots as $i => $slot): ?>
                    <tr class="vb-fade-in-up stagger-<?= min($i + 1, 6) ?> <?= !(int) $slot['is_active'] ? 'vb-row-inactive' : '' ?>">
                        <td class="vb-cell-name"><?= $dayNames[(int) $slot['day_of_week']] ?></td>
                        <td class="vb-text-secondary"><?= substr($slot['start_time'], 0, 5) ?></td>
                        <td class="vb-text-secondary"><?= substr($slot['end_time'], 0, 5) ?></td>
                        <td class="vb-text-center">
                            <span class="vb-badge vb-badge-neutral"><?= (int) $slot['max_capacity'] ?></span>
                        </td>
                        <td class="vb-text-center">
                            <span class="vb-badge vb-badge-neutral"><?= (int) ($slot['min_party_size'] ?? 1) ?></span>
                        </td>
                        <td class="vb-text-center">
                            <span class="vb-badge vb-badge-neutral"><?= (int) $slot['max_party_size'] ?></span>
                        </td>
                        <td class="vb-text-secondary">
                            <?= $slot['label'] ? htmlspecialchars($slot['label'], ENT_QUOTES, 'UTF-8') : '<span class="vb-text-ghost">—</span>' ?>
                        </td>
                        <td>
                            <?php if ((int) $slot['is_active']): ?>
                                <span class="vb-badge vb-badge-success"><?= __('admin.capacity_slots.status_active') ?></span>
                            <?php else: ?>
                                <span class="vb-badge vb-badge-default"><?= __('admin.capacity_slots.status_inactive') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="vb-text-right">
                            <div class="vb-action-group">
                                <form method="POST"
                                      action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/capacity-slots/<?= htmlspecialchars($slot['id'], ENT_QUOTES, 'UTF-8') ?>/toggle"
                                      class="vb-form-flush">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="vb-btn vb-btn-ghost vb-btn-sm"
                                            title="<?= (int) $slot['is_active'] ? __('admin.capacity_slots.btn_deactivate') : __('admin.capacity_slots.btn_activate') ?>">
                                        <i data-lucide="<?= (int) $slot['is_active'] ? 'eye-off' : 'eye' ?>" style="width: 14px; height: 14px;"></i>
                                    </button>
                                </form>
                                <form method="POST"
                                      action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/capacity-slots/<?= htmlspecialchars($slot['id'], ENT_QUOTES, 'UTF-8') ?>/delete"
                                      class="vb-form-flush"
                                      onsubmit="return confirm('<?= __('admin.capacity_slots.confirm_delete') ?>')">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="vb-btn vb-btn-ghost vb-btn-sm vb-btn-danger"
                                            title="<?= __('admin.capacity_slots.btn_delete') ?>">
                                        <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                    </button>
                                </form>
                            </div>
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
