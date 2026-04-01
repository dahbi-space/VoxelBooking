<?php
/**
 * Edit service page.
 */
$tenant = $tenant ?? [];
$service = $service ?? [];
$tenantId = $tenantId ?? '';

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.services.edit') ?></h2>
        <p class="vb-page-subtitle"><?= htmlspecialchars($service['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
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

<?php
$formAction = "/admin/tenants/{$tenantId}/services/{$service['id']}";
include __DIR__ . '/_form.php';
?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
