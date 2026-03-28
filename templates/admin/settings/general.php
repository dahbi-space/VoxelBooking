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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 6 9 17l-5-5"/></svg>
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <?php endif; ?>
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="vb-grid" style="gap: 1.5rem; grid-template-columns: 1fr 1fr;">
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
            <div class="vb-form-row">
                <div class="vb-form-group">
                    <label for="timezone" class="vb-label"><?= __('admin.settings.timezone_label') ?></label>
                    <input type="text" id="timezone" name="timezone" class="vb-input" value="<?= htmlspecialchars($settings['timezone'] ?? 'UTC', ENT_QUOTES, 'UTF-8') ?>" placeholder="Europe/Amsterdam">
                </div>
                <div class="vb-form-group">
                    <label for="date_format" class="vb-label"><?= __('admin.settings.date_format_label') ?></label>
                    <select id="date_format" name="date_format" class="vb-input">
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
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
            <span class="vb-info-label">VoxelBooking</span>
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
