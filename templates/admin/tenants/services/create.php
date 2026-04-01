<?php
/**
 * Create service page.
 */
$tenant = $tenant ?? [];
$tenantId = $tenantId ?? '';

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.services.new') ?></h2>
        <p class="vb-page-subtitle"><?= htmlspecialchars($tenant['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="vb-alert vb-alert-error">
        <i data-lucide="alert-circle"></i>
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php
$formAction = "/admin/tenants/{$tenantId}/services";
include __DIR__ . '/_form.php';
?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
