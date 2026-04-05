<?php
/**
 * Tenant Settings — General tab.
 *
 * Structure: card-header → form-grid with proper grouping.
 * Locale uses a labeled <select> from Locale::localeOptions() (controller-provided).
 * No inline styles — all layout via design system classes.
 *
 * Variables: $tenant, $tenantId, $csrfToken, $activeTab, $flash, $old, $localeOptions
 */
$tenant        = $tenant ?? [];
$tenantId      = $tenantId ?? '';
$old           = $old ?? null;
$localeOptions = $localeOptions ?? ['en' => 'English'];

$val = function (string $field, string $default = '') use ($tenant, $old): string {
    if ($old !== null && array_key_exists($field, $old)) {
        return htmlspecialchars((string) $old[$field], ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string) ($tenant[$field] ?? $default), ENT_QUOTES, 'UTF-8');
};

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.tenant_settings.title') ?></h2>
        <p class="vb-page-subtitle"><?= e($tenant['name'] ?? '') ?></p>
    </div>
</div>

<?php if ($flash): ?>
    <?php include __DIR__ . '/../../../partials/alert.php'; ?>
<?php endif; ?>

<?php include __DIR__ . '/_tabs.php'; ?>

<form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/settings" class="vb-animate-in">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Section 1: Identity -->
    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-card-header">
                <div class="vb-card-title"><?= __('admin.tenant_settings.section_identity') ?></div>
                <div class="vb-card-desc"><?= __('admin.tenant_settings.section_identity_desc') ?></div>
            </div>
            <div class="vb-form-grid vb-form-grid-2">
                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-name"><?= __('admin.tenant_settings.field_name') ?> <span class="vb-required">*</span></label>
                    <input type="text" class="vb-input" id="ts-name" name="name"
                           value="<?= $val('name') ?>" required maxlength="255">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-email"><?= __('admin.tenant_settings.field_email') ?> <span class="vb-required">*</span></label>
                    <input type="email" class="vb-input" id="ts-email" name="email"
                           value="<?= $val('email') ?>" required>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-slug"><?= __('admin.tenant_settings.field_slug') ?></label>
                    <input type="text" class="vb-input" id="ts-slug"
                           value="<?= e($tenant['slug'] ?? '') ?>" readonly>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_slug_hint') ?></span>
                    <?php $bookingUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/book/' . ($tenant['slug'] ?? ''); ?>
                    <div class="vb-settings-booking-url">
                        <code class="vb-settings-url-text"><?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?></code>
                        <button type="button"
                                class="vb-copy-btn"
                                data-copy-url="<?= htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') ?>"
                                @click="copyBookingUrl"
                                title="<?= __('admin.common.copy_booking_url') ?>">
                            <span class="vb-copy-icon"><i data-lucide="copy"></i></span>
                            <span class="vb-copy-check"><i data-lucide="check"></i></span>
                            <?= __('admin.common.copy_booking_url') ?>
                        </button>
                    </div>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-phone"><?= __('admin.tenant_settings.field_phone') ?></label>
                    <input type="tel" class="vb-input" id="ts-phone" name="phone"
                           value="<?= $val('phone') ?>">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-pattern"><?= __('admin.tenant_settings.field_pattern') ?></label>
                    <input type="text" class="vb-input" id="ts-pattern"
                           value="<?= e($tenant['booking_pattern'] ?? '') ?>" readonly>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_pattern_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Regional -->
    <div class="vb-settings-section">
        <div class="vb-card">
            <div class="vb-card-header">
                <div class="vb-card-title"><?= __('admin.tenant_settings.section_regional') ?></div>
                <div class="vb-card-desc"><?= __('admin.tenant_settings.section_regional_desc') ?></div>
            </div>
            <div class="vb-form-grid vb-form-grid-2">
                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-timezone"><?= __('admin.tenant_settings.field_timezone') ?></label>
                    <input type="text" class="vb-input" id="ts-timezone" name="timezone"
                           value="<?= $val('timezone', 'UTC') ?>" maxlength="100">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-currency"><?= __('admin.tenant_settings.field_currency') ?></label>
                    <input type="text" class="vb-input vb-input-narrow" id="ts-currency" name="currency"
                           value="<?= $val('currency', 'EUR') ?>" maxlength="3">
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-locale"><?= __('admin.tenant_settings.field_locale') ?></label>
                    <select class="vb-input" id="ts-locale" name="locale">
                        <?php
                        $currentLocale = ($old !== null && array_key_exists('locale', $old))
                            ? $old['locale']
                            : ($tenant['locale'] ?? 'en');
                        // Ensure current value always appears (e.g. old input for a not-yet-translated locale)
                        $effectiveOptions = $localeOptions;
                        if ($currentLocale !== '' && !isset($effectiveOptions[$currentLocale])) {
                            $effectiveOptions[$currentLocale] = $currentLocale;
                        }
                        foreach ($effectiveOptions as $code => $label):
                        ?>
                        <option value="<?= $code ?>" <?= $currentLocale === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-week-start"><?= __('admin.tenant_settings.field_week_start') ?></label>
                    <select class="vb-input" id="ts-week-start" name="week_start">
                        <?php
                        $currentWeekStart = $old !== null && array_key_exists('week_start', $old)
                            ? $old['week_start']
                            : ($tenant['week_start'] ?? '');
                        $weekDays = [
                            '' => __('admin.tenant_settings.field_week_start_auto'),
                            '1' => __('booking.days.1'), // Monday
                            '0' => __('booking.days.0'), // Sunday
                            '6' => __('booking.days.6'), // Saturday
                        ];
                        foreach ($weekDays as $wv => $wl):
                        ?>
                        <option value="<?= $wv ?>" <?= (string) $currentWeekStart === (string) $wv ? 'selected' : '' ?>><?= e($wl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_week_start_hint') ?></span>
                </div>

                <div class="vb-settings-field">
                    <label class="vb-label" for="ts-time-format"><?= __('admin.tenant_settings.field_time_format') ?></label>
                    <select class="vb-input" id="ts-time-format" name="time_format">
                        <?php
                        $currentTimeFormat = $old !== null && array_key_exists('time_format', $old)
                            ? $old['time_format']
                            : ($tenant['time_format'] ?? '');
                        $timeFormats = [
                            ''    => __('admin.tenant_settings.field_time_format_auto'),
                            '12h' => __('admin.tenant_settings.field_time_format_12h'),
                            '24h' => __('admin.tenant_settings.field_time_format_24h'),
                        ];
                        foreach ($timeFormats as $tv => $tl):
                        ?>
                        <option value="<?= $tv ?>" <?= $currentTimeFormat === $tv ? 'selected' : '' ?>><?= e($tl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="vb-settings-hint"><?= __('admin.tenant_settings.field_time_format_hint') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="vb-form-actions">
        <button type="submit" class="vb-btn vb-btn-primary" id="save-general-btn">
            <i data-lucide="save"></i>
            <?= __('admin.settings.save_button') ?>
        </button>
    </div>
</form>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
