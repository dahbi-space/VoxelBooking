<?php
/**
 * Create event — admin page for event-pattern tenants.
 *
 * Variables: $user, $version, $csrfToken, $tenant, $tenantId, $flash, $old
 */
$activePage = 'events';
ob_start();
?>

<?php if ($flash ?? null): ?>
    <div class="vb-alert vb-alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?php if ($flash['type'] === 'success'): ?>
            <i data-lucide="check"></i>
        <?php else: ?>
            <i data-lucide="alert-circle"></i>
        <?php endif; ?>
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="vb-page-header">
    <div>
        <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/events"
           class="vb-btn vb-btn-ghost vb-btn-sm" style="margin-bottom: 8px;">
            <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
            <?= __('admin.events.title') ?>
        </a>
        <h2 class="vb-page-title">
            <i data-lucide="plus" class="vb-page-header-icon"></i>
            <?= __('admin.events.create_title') ?>
        </h2>
    </div>
</div>

<div class="vb-card vb-animate-in">
    <div class="vb-card-body">
        <form method="POST"
              action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/events"
              id="event-create-form">
            <?php include __DIR__ . '/_form.php'; ?>

            <div class="vb-form-actions">
                <button type="submit" class="vb-btn vb-btn-primary" id="btn-save-event">
                    <i data-lucide="save" style="width: 14px; height: 14px;"></i>
                    <?= __('admin.events.create_title') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
