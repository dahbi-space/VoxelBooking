<?php
/**
 * Create Tenant form.
 *
 * Variables: $user, $version, $csrfToken, $flash, $pageTitle, $activePage
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
        <h2 class="vb-page-title"><?= __('admin.tenants.create') ?></h2>
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

<div class="vb-card vb-fade-in-up">
    <div class="vb-card-header">
        <div class="vb-card-title"><?= __('admin.tenants.create') ?></div>
    </div>
    <form method="POST" action="/admin/tenants/create">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="vb-form-group">
            <label for="tenant_name" class="vb-label"><?= __('admin.tenants.name') ?> *</label>
            <input type="text" id="tenant_name" name="name" class="vb-input" required
                   placeholder="Acme Hair Studio">
        </div>

        <div class="vb-form-group">
            <label for="tenant_slug" class="vb-label"><?= __('admin.tenants.slug') ?></label>
            <input type="text" id="tenant_slug" name="slug" class="vb-input"
                   placeholder="acme-hair-studio">
            <span class="vb-hint"><?= __('admin.tenants.slug_help') ?></span>
        </div>

        <div class="vb-form-group">
            <label for="tenant_email" class="vb-label"><?= __('admin.tenants.email') ?> *</label>
            <input type="email" id="tenant_email" name="email" class="vb-input" required
                   placeholder="hello@example.com">
        </div>

        <div class="vb-form-group">
            <label class="vb-label"><?= __('admin.tenants.pattern') ?></label>
            <div class="vb-radio-group">
                <?php
                $patterns = [
                    'timeslot' => ['icon' => 'clock', 'label' => __('admin.tenants.pattern_timeslot')],
                    'resource' => ['icon' => 'users', 'label' => __('admin.tenants.pattern_resource')],
                    'capacity' => ['icon' => 'layers', 'label' => __('admin.tenants.pattern_capacity')],
                    'event'    => ['icon' => 'calendar-days', 'label' => __('admin.tenants.pattern_event')],
                ];
                foreach ($patterns as $value => $info):
                ?>
                <label class="vb-radio-card">
                    <input type="radio" name="booking_pattern" value="<?= $value ?>"
                           <?= $value === 'timeslot' ? 'checked' : '' ?>>
                    <i data-lucide="<?= $info['icon'] ?>"></i>
                    <span><?= $info['label'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vb-form-row">
            <div class="vb-form-group">
                <label for="tenant_timezone" class="vb-label"><?= __('admin.tenants.timezone') ?></label>
                <input type="text" id="tenant_timezone" name="timezone" class="vb-input"
                       value="UTC" placeholder="Europe/Amsterdam">
            </div>
            <div class="vb-form-group">
                <label for="tenant_currency" class="vb-label"><?= __('admin.tenants.currency') ?></label>
                <input type="text" id="tenant_currency" name="currency" class="vb-input"
                       value="EUR" placeholder="EUR" maxlength="3">
            </div>
        </div>

        <div class="vb-form-group">
            <label for="tenant_brand_color" class="vb-label"><?= __('admin.tenants.brand_color') ?></label>
            <div class="vb-color-field">
                <input type="color" id="tenant_brand_color" name="brand_color"
                       value="#2563EB" class="vb-color-input">
                <span class="vb-color-hex">#2563EB</span>
            </div>
        </div>

        <div class="vb-form-actions">
            <button type="submit" class="vb-btn vb-btn-primary">
                <i data-lucide="plus"></i>
                <?= __('admin.tenants.create') ?>
            </button>
            <a href="/admin/tenants" class="vb-btn vb-btn-ghost">
                <?= __('admin.common.cancel') ?>
            </a>
        </div>
    </form>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
