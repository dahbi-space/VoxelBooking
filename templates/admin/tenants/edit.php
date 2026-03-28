<?php
/**
 * Edit Tenant form (pre-populated).
 *
 * Variables: $user, $version, $csrfToken, $tenant, $flash, $pageTitle, $activePage
 */
$activePage = 'tenants';

ob_start();
?>

<div class="vb-page-header">
    <div>
        <a href="/admin/tenants" class="vb-back-link">
            <i data-lucide="chevron-left"></i>
            <?= __('admin.tenants.title') ?>
        </a>
        <h2 class="vb-page-title"><?= __('admin.tenants.edit') ?></h2>
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

<div class="vb-grid vb-grid-2 vb-fade-in-up">
    <!-- Edit Form -->
    <div class="vb-card">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.tenants.edit') ?></div>
        </div>
        <form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenant['id'], ENT_QUOTES, 'UTF-8') ?>/edit">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="vb-form-group">
                <label for="tenant_name" class="vb-label"><?= __('admin.tenants.name') ?> *</label>
                <input type="text" id="tenant_name" name="name" class="vb-input" required
                       value="<?= htmlspecialchars($tenant['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="vb-form-group">
                <label for="tenant_slug" class="vb-label"><?= __('admin.tenants.slug') ?></label>
                <input type="text" id="tenant_slug" name="slug" class="vb-input"
                       value="<?= htmlspecialchars($tenant['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <span class="vb-hint"><?= __('admin.tenants.slug_help') ?></span>
            </div>

            <div class="vb-form-group">
                <label for="tenant_email" class="vb-label"><?= __('admin.tenants.email') ?> *</label>
                <input type="email" id="tenant_email" name="email" class="vb-input" required
                       value="<?= htmlspecialchars($tenant['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="vb-form-row">
                <div class="vb-form-group">
                    <label for="tenant_timezone" class="vb-label"><?= __('admin.tenants.timezone') ?></label>
                    <input type="text" id="tenant_timezone" name="timezone" class="vb-input"
                           value="<?= htmlspecialchars($tenant['timezone'] ?? 'UTC', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="vb-form-group">
                    <label for="tenant_currency" class="vb-label"><?= __('admin.tenants.currency') ?></label>
                    <input type="text" id="tenant_currency" name="currency" class="vb-input"
                           value="<?= htmlspecialchars($tenant['currency'] ?? 'EUR', ENT_QUOTES, 'UTF-8') ?>" maxlength="3">
                </div>
            </div>

            <div class="vb-form-group">
                <label for="tenant_brand_color" class="vb-label"><?= __('admin.tenants.brand_color') ?></label>
                <div class="vb-color-field">
                    <input type="color" id="tenant_brand_color" name="brand_color"
                           value="<?= htmlspecialchars($tenant['brand_color'] ?? '#2563EB', ENT_QUOTES, 'UTF-8') ?>"
                           class="vb-color-input">
                    <span class="vb-color-hex"><?= htmlspecialchars($tenant['brand_color'] ?? '#2563EB', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <div class="vb-form-actions">
                <button type="submit" class="vb-btn vb-btn-primary">
                    <i data-lucide="check"></i>
                    <?= __('admin.settings.save_button') ?>
                </button>
                <a href="/admin/tenants" class="vb-btn vb-btn-ghost">
                    <?= __('admin.common.cancel') ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Tenant Info -->
    <div class="vb-card">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.tenants.status') ?></div>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.tenants.status') ?></span>
            <span class="vb-info-value">
                <?php
                $statusClass = match ($tenant['status'] ?? 'active') {
                    'active'   => 'vb-badge-success',
                    'paused'   => 'vb-badge-warning',
                    'archived' => 'vb-badge-default',
                    default    => 'vb-badge-default',
                };
                ?>
                <span class="vb-badge <?= $statusClass ?>"><?= __('admin.tenants.status_' . ($tenant['status'] ?? 'active')) ?></span>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.tenants.pattern') ?></span>
            <span class="vb-info-value">
                <span class="vb-badge vb-badge-default"><?= htmlspecialchars(ucfirst($tenant['booking_pattern'] ?? 'timeslot'), ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.tenants.created') ?></span>
            <span class="vb-info-value"><?= htmlspecialchars($tenant['created_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.tenants.view_booking_page') ?></span>
            <span class="vb-info-value">
                <a href="/book/<?= htmlspecialchars($tenant['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener" class="vb-link">
                    /book/<?= htmlspecialchars($tenant['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    <i data-lucide="external-link" style="width: 12px; height: 12px;"></i>
                </a>
            </span>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
