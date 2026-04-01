<?php
/**
 * Service form partial — shared between create and edit.
 *
 * Variables expected:
 *   $service       — existing record (null for create)
 *   $staff         — active staff list
 *   $linkedStaffIds — currently linked staff IDs (edit only)
 *   $formAction    — POST target URL
 *   $tenantId
 *   $csrfToken
 *   $old           — old input from validation failure
 */
$service = $service ?? null;
$linkedStaffIds = $linkedStaffIds ?? [];
$old = $old ?? [];
$currentStaffIds = !empty($old) ? ($old['staff_ids'] ?? []) : $linkedStaffIds;

$v = fn(string $field, $default = '') => htmlspecialchars(
    (string) ($old[$field] ?? ($service[$field] ?? $default)),
    ENT_QUOTES,
    'UTF-8'
);
?>

<form method="POST" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <div class="vb-card">
        <div class="vb-form-grid vb-form-grid-2" style="padding: 1.25rem;">
            <div class="vb-form-group" style="grid-column: 1 / -1;">
                <label class="vb-label" for="svc-name"><?= __('admin.services.field_name') ?> *</label>
                <input type="text" class="vb-input" id="svc-name" name="name"
                       value="<?= $v('name') ?>" required maxlength="255" autofocus>
            </div>

            <div class="vb-form-group" style="grid-column: 1 / -1;">
                <label class="vb-label" for="svc-desc"><?= __('admin.services.field_description') ?></label>
                <textarea class="vb-input" id="svc-desc" name="description" rows="3"
                          style="resize: vertical;"><?= $v('description') ?></textarea>
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="svc-duration"><?= __('admin.services.field_duration') ?> *</label>
                <input type="number" class="vb-input" id="svc-duration" name="duration_minutes"
                       value="<?= $v('duration_minutes', '30') ?>" required min="1" step="1">
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="svc-price"><?= __('admin.services.field_price') ?></label>
                <input type="number" class="vb-input" id="svc-price" name="price"
                       value="<?= $v('price') ?>" min="0" step="0.01">
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="svc-price-label">
                    <?= __('admin.services.field_price_label') ?>
                    <span class="vb-label-hint"><?= __('admin.services.field_price_label_hint') ?></span>
                </label>
                <input type="text" class="vb-input" id="svc-price-label" name="price_label"
                       value="<?= $v('price_label') ?>" maxlength="100">
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="svc-category">
                    <?= __('admin.services.field_category') ?>
                    <span class="vb-label-hint"><?= __('admin.services.field_category_hint') ?></span>
                </label>
                <input type="text" class="vb-input" id="svc-category" name="category"
                       value="<?= $v('category') ?>" maxlength="100">
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="svc-color"><?= __('admin.services.field_color') ?></label>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="color" id="svc-color" name="color"
                           value="<?= $v('color', '#6366F1') ?>"
                           style="width: 36px; height: 36px; padding: 2px; border: 1px solid var(--vb-border); border-radius: var(--radius-md); cursor: pointer;">
                    <input type="text" class="vb-input" id="svc-color-text"
                           value="<?= $v('color', '#6366F1') ?>"
                           pattern="^#[0-9a-fA-F]{6}$" maxlength="7" style="max-width: 100px; font-family: var(--vb-font-mono, monospace); font-size: 0.8125rem;"
                           oninput="document.getElementById('svc-color').value = this.value">
                </div>
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="svc-order"><?= __('admin.services.field_sort_order') ?></label>
                <input type="number" class="vb-input" id="svc-order" name="sort_order"
                       value="<?= $v('sort_order', '0') ?>" min="0" step="1">
            </div>
        </div>
    </div>

    <!-- Staff assignment -->
    <?php if (!empty($staff)): ?>
    <div class="vb-card" style="margin-top: 1rem;">
        <div class="vb-card-header">
            <h3 class="vb-card-title">
                <i data-lucide="users" style="width: 15px; height: 15px;"></i>
                <?= __('admin.services.field_staff') ?>
            </h3>
        </div>
        <div style="padding: 1rem 1.25rem;">
            <p class="vb-text-secondary" style="font-size: 0.8125rem; margin: 0 0 0.75rem;"><?= __('admin.services.field_staff_hint') ?></p>
            <div class="vb-checkbox-grid">
                <?php foreach ($staff as $s): ?>
                <label class="vb-checkbox-label">
                    <input type="checkbox" name="staff_ids[]"
                           value="<?= htmlspecialchars($s['id'], ENT_QUOTES, 'UTF-8') ?>"
                           <?= in_array($s['id'], $currentStaffIds, true) ? 'checked' : '' ?>>
                    <span class="vb-checkbox-text">
                        <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($s['title']): ?>
                            <span class="vb-checkbox-meta"><?= htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="vb-form-actions" style="margin-top: 1.25rem;">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-service-btn">
            <i data-lucide="save" style="width: 16px; height: 16px;"></i>
            <?= $service ? __('admin.services.updated') : __('admin.services.created') ?>
        </button>
        <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/services"
           class="vb-btn vb-btn-ghost"><?= __('admin.common.cancel') ?></a>
    </div>
</form>
