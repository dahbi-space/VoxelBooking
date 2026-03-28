<?php
/**
 * General Settings — Visual Design §16.
 *
 * Section cards: Application + System information.
 * Uses design system classes exclusively — no inline style blocks.
 *
 * Variables: $user, $version, $csrfToken, $settings, $flash, $pageTitle
 */
$activePage = 'settings.general';
$activeTab = 'general';

ob_start();

include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>

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

<div class="vb-grid vb-grid-2">
    <!-- Application Settings -->
    <div class="vb-card vb-fade-in-up stagger-1">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.settings.app_title') ?></div>
            <div class="vb-card-desc"><?= __('admin.settings.app_desc') ?></div>
        </div>
        <form method="POST" action="/admin/settings">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="vb-form-group">
                <label for="app_name" class="vb-label"><?= __('admin.settings.app_name_label') ?></label>
                <input type="text" id="app_name" name="app_name" class="vb-input" value="<?= htmlspecialchars($settings['app_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="VoxelBooking">
            </div>
            <div class="vb-form-group">
                <label for="app_url" class="vb-label"><?= __('admin.settings.app_url_label') ?></label>
                <input type="url" id="app_url" name="app_url" class="vb-input" value="<?= htmlspecialchars($settings['app_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://booking.yourdomain.com">
            </div>
            <div class="vb-form-group">
                <label for="brand_url" class="vb-label"><?= __('admin.settings.brand_url_label') ?></label>
                <input type="url" id="brand_url" name="brand_url" class="vb-input" value="<?= htmlspecialchars($settings['brand_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://voxelbooking.com">
                <span class="vb-hint"><?= __('admin.settings.brand_url_hint') ?></span>
            </div>
            <div class="vb-form-row">
                <div class="vb-form-group">
                    <label for="timezone" class="vb-label"><?= __('admin.settings.timezone_label') ?></label>
                    <input type="text" id="timezone" name="timezone" class="vb-input" value="<?= htmlspecialchars($settings['timezone'] ?? 'UTC', ENT_QUOTES, 'UTF-8') ?>" placeholder="Europe/Amsterdam">
                </div>
                <div class="vb-form-group">
                    <label for="date_format" class="vb-label"><?= __('admin.settings.date_format_label') ?></label>
                    <select id="date_format" name="date_format" class="vb-select">
                        <?php
                        $formats = ['Y-m-d' => '2026-03-27', 'd/m/Y' => '27/03/2026', 'm/d/Y' => '03/27/2026', 'd-m-Y' => '27-03-2026', 'd.m.Y' => '27.03.2026'];
                        foreach ($formats as $fmt => $example):
                        ?>
                            <option value="<?= $fmt ?>" <?= ($settings['date_format'] ?? 'Y-m-d') === $fmt ? 'selected' : '' ?>><?= $example ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="vb-form-actions">
                <button type="submit" class="vb-btn vb-btn-primary">
                    <i data-lucide="check"></i>
                    <?= __('admin.settings.save_button') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- System Information -->
    <div class="vb-card vb-fade-in-up stagger-2">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.settings.system_title') ?></div>
            <div class="vb-card-desc"><?= __('admin.settings.system_desc') ?></div>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="vb-info-value"><code>v<?= htmlspecialchars($version ?? '0.0.0', ENT_QUOTES, 'UTF-8') ?></code></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label">PHP</span>
            <span class="vb-info-value"><code><?= PHP_VERSION ?></code></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.settings.server') ?></span>
            <span class="vb-info-value"><code><?= htmlspecialchars(php_uname('s') . ' ' . php_uname('r'), ENT_QUOTES, 'UTF-8') ?></code></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.settings.database') ?></span>
            <span class="vb-info-value"><code>MySQL</code></span>
        </div>
        <div class="vb-info-row">
            <span class="vb-info-label"><?= __('admin.settings.memory_limit') ?></span>
            <span class="vb-info-value"><code><?= ini_get('memory_limit') ?></code></span>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
