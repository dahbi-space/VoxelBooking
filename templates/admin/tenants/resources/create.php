<?php
/**
 * Create resource — tenant-scoped, resource-pattern only.
 *
 * Variables: $tenant, $tenantId, $csrfToken, $old, $flash
 */
$tenant = $tenant ?? [];
$tenantId = $tenantId ?? '';

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.resources.new') ?></h2>
    </div>
    <div class="vb-page-actions">
        <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/resources"
           class="vb-btn vb-btn-ghost">
            <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
            <?= __('admin.resources.title') ?>
        </a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="vb-alert vb-alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?php if ($flash['type'] === 'success'): ?>
            <i data-lucide="check"></i>
        <?php else: ?>
            <i data-lucide="alert-circle"></i>
        <?php endif; ?>
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="vb-card vb-animate-in">
    <form method="POST"
          action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/resources"
          id="resource-form">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <?php include __DIR__ . '/_form.php'; ?>

        <div class="vb-form-actions">
            <button type="submit" class="vb-btn vb-btn-primary" id="save-resource-btn">
                <i data-lucide="check" style="width: 16px; height: 16px;"></i>
                <?= __('admin.common.save') ?>
            </button>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
