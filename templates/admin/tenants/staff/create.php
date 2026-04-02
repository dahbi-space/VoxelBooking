<?php
/**
 * Create Staff Member — tenant-scoped.
 *
 * Fields: name*, email*, phone, title, bio, sort_order, service assignments.
 *
 * Variables: $tenant, $services, $old, $tenantId, $csrfToken, $flash
 */
$tenant = $tenant ?? [];
$services = $services ?? [];
$old = $old ?? [];
$tenantId = $tenantId ?? '';
$oldServices = $old['service_ids'] ?? [];

ob_start();
?>

<div class="vb-page-header">
    <div>
        <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/staff" class="vb-back-link">
            <i data-lucide="chevron-left"></i>
            <?= __('admin.staff.title') ?>
        </a>
        <h2 class="vb-page-title"><?= __('admin.staff.new') ?></h2>
    </div>
</div>

<?php if ($flash): ?>
    <?php include __DIR__ . '/../../../partials/alert.php'; ?>
<?php endif; ?>

<div class="vb-grid vb-grid-2 vb-fade-in-up">
    <div class="vb-card">
        <form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/staff/create"
              id="create-staff-form">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="vb-form-row">
                <div class="vb-form-group">
                    <label for="staff_name" class="vb-label"><?= __('admin.staff.label_name') ?> *</label>
                    <input type="text" id="staff_name" name="name" class="vb-input" required
                           value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="<?= __('admin.staff.placeholder_name') ?>">
                </div>
                <div class="vb-form-group">
                    <label for="staff_email" class="vb-label"><?= __('admin.staff.label_email') ?> *</label>
                    <input type="email" id="staff_email" name="email" class="vb-input" required
                           value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="<?= __('admin.staff.placeholder_email') ?>">
                </div>
            </div>

            <div class="vb-form-row">
                <div class="vb-form-group">
                    <label for="staff_phone" class="vb-label"><?= __('admin.staff.label_phone') ?></label>
                    <input type="tel" id="staff_phone" name="phone" class="vb-input"
                           value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="<?= __('admin.staff.placeholder_phone') ?>">
                </div>
                <div class="vb-form-group">
                    <label for="staff_title" class="vb-label"><?= __('admin.staff.label_title') ?></label>
                    <input type="text" id="staff_title" name="title" class="vb-input"
                           value="<?= htmlspecialchars($old['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="<?= __('admin.staff.placeholder_title') ?>">
                </div>
            </div>

            <div class="vb-form-group">
                <label for="staff_bio" class="vb-label"><?= __('admin.staff.label_bio') ?></label>
                <textarea id="staff_bio" name="bio" class="vb-input vb-textarea" rows="3"
                          placeholder="<?= __('admin.staff.placeholder_bio') ?>"><?= htmlspecialchars($old['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="vb-form-group">
                <label for="staff_sort_order" class="vb-label"><?= __('admin.staff.label_sort_order') ?></label>
                <input type="number" id="staff_sort_order" name="sort_order" class="vb-input" min="0"
                       value="<?= htmlspecialchars((string) ($old['sort_order'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                       style="max-width: 100px;">
            </div>

            <div class="vb-form-actions">
                <button type="submit" class="vb-btn vb-btn-primary">
                    <i data-lucide="check"></i>
                    <?= __('admin.staff.new') ?>
                </button>
                <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/staff"
                   class="vb-btn vb-btn-ghost">
                    <?= __('admin.common.cancel') ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Service Assignments -->
    <div class="vb-card">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.staff.label_services') ?></div>
        </div>
        <?php if (empty($services)): ?>
            <div class="vb-empty-state-inline">
                <p class="vb-text-tertiary"><?= __('admin.staff.no_services_available') ?></p>
            </div>
        <?php else: ?>
            <div class="vb-checkbox-list">
                <?php foreach ($services as $svc): ?>
                <label class="vb-checkbox-item" for="svc_<?= htmlspecialchars($svc['id'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="checkbox"
                           id="svc_<?= htmlspecialchars($svc['id'], ENT_QUOTES, 'UTF-8') ?>"
                           name="service_ids[]"
                           value="<?= htmlspecialchars($svc['id'], ENT_QUOTES, 'UTF-8') ?>"
                           form="create-staff-form"
                           <?= in_array($svc['id'], $oldServices) ? 'checked' : '' ?>>
                    <span class="vb-checkbox-label"><?= htmlspecialchars($svc['name'], ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
