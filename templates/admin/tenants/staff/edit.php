<?php
/**
 * Edit Staff Member — tenant-scoped.
 *
 * Fields: name*, email*, phone, title, bio, sort_order, service assignments.
 *
 * Variables: $tenant, $member, $services, $linkedServiceIds, $old, $tenantId, $csrfToken, $flash
 */
$tenant = $tenant ?? [];
$member = $member ?? [];
$services = $services ?? [];
$linkedServiceIds = $linkedServiceIds ?? [];
$old = $old ?? [];
$tenantId = $tenantId ?? '';
$staffId = $member['id'] ?? '';
$currentServices = !empty($old) ? ($old['service_ids'] ?? []) : $linkedServiceIds;

ob_start();
?>

<div class="vb-page-header">
    <div>
        <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/staff" class="vb-back-link">
            <i data-lucide="chevron-left"></i>
            <?= __('admin.staff.title') ?>
        </a>
        <h2 class="vb-page-title"><?= __('admin.staff.edit') ?></h2>
    </div>
    <div class="vb-page-actions">
        <?php if ($member['is_active']): ?>
            <span class="vb-badge vb-badge-success"><?= __('admin.staff.status_active') ?></span>
        <?php else: ?>
            <span class="vb-badge vb-badge-default"><?= __('admin.staff.status_inactive') ?></span>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash): ?>
    <?php include __DIR__ . '/../../../partials/alert.php'; ?>
<?php endif; ?>

<div class="vb-card p-6 vb-fade-in-up">
    <form method="POST"
          action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/staff/<?= htmlspecialchars($staffId, ENT_QUOTES, 'UTF-8') ?>/edit"
          id="edit-staff-form">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="vb-form-row">
            <div class="vb-form-group">
                <label for="staff_name" class="vb-label"><?= __('admin.staff.label_name') ?> <span class="text-red-500">*</span></label>
                <input type="text" id="staff_name" name="name" class="vb-input" required
                       value="<?= htmlspecialchars($old['name'] ?? $member['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= __('admin.staff.placeholder_name') ?>">
            </div>
            <div class="vb-form-group">
                <label for="staff_email" class="vb-label"><?= __('admin.staff.label_email') ?> <span class="text-red-500">*</span></label>
                <input type="email" id="staff_email" name="email" class="vb-input" required
                       value="<?= htmlspecialchars($old['email'] ?? $member['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= __('admin.staff.placeholder_email') ?>">
            </div>
        </div>

        <div class="vb-form-row">
            <div class="vb-form-group">
                <label for="staff_phone" class="vb-label"><?= __('admin.staff.label_phone') ?></label>
                <input type="tel" id="staff_phone" name="phone" class="vb-input"
                       value="<?= htmlspecialchars($old['phone'] ?? $member['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= __('admin.staff.placeholder_phone') ?>">
            </div>
            <div class="vb-form-group">
                <label for="staff_title" class="vb-label"><?= __('admin.staff.label_title') ?></label>
                <input type="text" id="staff_title" name="title" class="vb-input"
                       value="<?= htmlspecialchars($old['title'] ?? $member['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= __('admin.staff.placeholder_title') ?>">
            </div>
        </div>

        <div class="vb-form-group col-span-full">
            <label for="staff_bio" class="vb-label"><?= __('admin.staff.label_bio') ?></label>
            <textarea id="staff_bio" name="bio" class="vb-input resize-y" rows="3"
                      placeholder="<?= __('admin.staff.placeholder_bio') ?>"><?= htmlspecialchars($old['bio'] ?? $member['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="vb-form-group">
            <label for="staff_sort_order" class="vb-label"><?= __('admin.staff.label_sort_order') ?></label>
            <input type="number" id="staff_sort_order" name="sort_order" class="vb-input max-w-[100px]" min="0"
                   value="<?= htmlspecialchars((string) ($old['sort_order'] ?? $member['sort_order'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="vb-section-divider"></div>

        <div class="vb-form-group">
            <label class="vb-label vb-icon-label mb-1">
                <i data-lucide="layers"></i>
                <?= __('admin.staff.label_services') ?>
            </label>
            <?php if (empty($services)): ?>
                <p class="vb-text-tertiary text-sm"><?= __('admin.staff.no_services_available') ?></p>
            <?php else: ?>
                <div class="vb-checkbox-grid mt-4">
                    <?php foreach ($services as $svc): ?>
                    <label class="vb-checkbox-label">
                        <input type="checkbox"
                               name="service_ids[]"
                               value="<?= htmlspecialchars($svc['id'], ENT_QUOTES, 'UTF-8') ?>"
                               <?= in_array($svc['id'], $currentServices) ? 'checked' : '' ?>>
                        <span class="vb-checkbox-text"><?= htmlspecialchars($svc['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="vb-form-actions mt-8">
            <button type="submit" class="vb-btn vb-btn-primary">
                <i data-lucide="check" class="w-4 h-4"></i>
                <?= __('admin.settings.save_button') ?>
            </button>
            <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/staff"
               class="vb-btn vb-btn-ghost">
                <?= __('admin.common.cancel') ?>
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
