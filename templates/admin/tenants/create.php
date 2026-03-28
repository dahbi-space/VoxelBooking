<?php
/**
 * Create Tenant form — premium design with pattern cards and color picker.
 *
 * Uses wizard-grade components:
 * - vb-pattern-cards: 2×2 grid of selectable booking pattern cards
 * - vb-color-field:   swatch + text input with bidirectional sync
 * - vb-select:        premium styled selects for timezone and currency
 *
 * Alpine components: patternCards, colorSync (registered in admin/app.js)
 *
 * Variables: $user, $version, $csrfToken, $flash, $pageTitle, $activePage
 */
$activePage = 'tenants';

$timezones = [
    'UTC', 'Europe/London', 'Europe/Amsterdam', 'Europe/Berlin', 'Europe/Paris',
    'Europe/Madrid', 'Europe/Rome', 'Europe/Zurich', 'Europe/Stockholm',
    'Europe/Oslo', 'Europe/Helsinki', 'Europe/Warsaw', 'Europe/Prague',
    'Europe/Vienna', 'Europe/Brussels', 'Europe/Lisbon', 'Europe/Athens',
    'Europe/Bucharest', 'Europe/Istanbul', 'Europe/Moscow',
    'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
    'America/Toronto', 'America/Vancouver', 'America/Sao_Paulo', 'America/Mexico_City',
    'America/Argentina/Buenos_Aires',
    'Asia/Dubai', 'Asia/Kolkata', 'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo',
    'Asia/Shanghai', 'Asia/Seoul', 'Asia/Hong_Kong', 'Asia/Jakarta',
    'Australia/Sydney', 'Australia/Melbourne', 'Pacific/Auckland',
    'Africa/Cairo', 'Africa/Johannesburg', 'Africa/Lagos',
];

$currencies = [
    'EUR' => '€ EUR — Euro',
    'USD' => '$ USD — US Dollar',
    'GBP' => '£ GBP — British Pound',
    'CHF' => 'CHF — Swiss Franc',
    'SEK' => 'SEK — Swedish Krona',
    'NOK' => 'NOK — Norwegian Krone',
    'DKK' => 'DKK — Danish Krone',
    'PLN' => 'PLN — Polish Złoty',
    'CZK' => 'CZK — Czech Koruna',
    'HUF' => 'HUF — Hungarian Forint',
    'RON' => 'RON — Romanian Leu',
    'BGN' => 'BGN — Bulgarian Lev',
    'HRK' => 'HRK — Croatian Kuna',
    'CAD' => 'CAD — Canadian Dollar',
    'AUD' => 'AUD — Australian Dollar',
    'NZD' => 'NZD — New Zealand Dollar',
    'BRL' => 'BRL — Brazilian Real',
    'MXN' => 'MXN — Mexican Peso',
    'ARS' => 'ARS — Argentine Peso',
    'JPY' => '¥ JPY — Japanese Yen',
    'CNY' => '¥ CNY — Chinese Yuan',
    'KRW' => '₩ KRW — South Korean Won',
    'INR' => '₹ INR — Indian Rupee',
    'SGD' => 'SGD — Singapore Dollar',
    'THB' => '฿ THB — Thai Baht',
    'IDR' => 'IDR — Indonesian Rupiah',
    'AED' => 'AED — UAE Dirham',
    'ZAR' => 'ZAR — South African Rand',
    'TRY' => '₺ TRY — Turkish Lira',
    'ILS' => '₪ ILS — Israeli Shekel',
    'EGP' => 'EGP — Egyptian Pound',
    'NGN' => '₦ NGN — Nigerian Naira',
];

$patterns = [
    'timeslot' => [
        'icon'  => 'clock',
        'name'  => __('admin.tenants.pattern_timeslot'),
        'desc'  => __('admin.tenants.pattern_timeslot_desc'),
    ],
    'resource' => [
        'icon'  => 'users',
        'name'  => __('admin.tenants.pattern_resource'),
        'desc'  => __('admin.tenants.pattern_resource_desc'),
    ],
    'capacity' => [
        'icon'  => 'layers',
        'name'  => __('admin.tenants.pattern_capacity'),
        'desc'  => __('admin.tenants.pattern_capacity_desc'),
    ],
    'event' => [
        'icon'  => 'calendar-days',
        'name'  => __('admin.tenants.pattern_event'),
        'desc'  => __('admin.tenants.pattern_event_desc'),
    ],
];

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

        <!-- Booking Pattern Cards -->
        <div class="vb-form-group" x-data="patternCards">
            <label class="vb-label"><?= __('admin.tenants.pattern') ?></label>
            <div class="vb-pattern-cards">
                <?php foreach ($patterns as $value => $info): ?>
                <label class="vb-pattern-card" @click="select('<?= $value ?>')">
                    <input type="radio" name="booking_pattern" value="<?= $value ?>"
                           <?= $value === 'timeslot' ? 'checked' : '' ?>>
                    <span class="vb-pattern-card-icon">
                        <i data-lucide="<?= $info['icon'] ?>"></i>
                    </span>
                    <span class="vb-pattern-card-name"><?= $info['name'] ?></span>
                    <span class="vb-pattern-card-desc"><?= $info['desc'] ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Timezone & Currency -->
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label for="tenant_timezone" class="vb-label vb-icon-label">
                    <i data-lucide="globe"></i>
                    <?= __('admin.tenants.timezone') ?>
                </label>
                <select id="tenant_timezone" name="timezone" class="vb-select">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $tz ?>" <?= $tz === 'UTC' ? 'selected' : '' ?>>
                            <?= str_replace(['_', '/'], [' ', ' / '], $tz) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vb-form-group">
                <label for="tenant_currency" class="vb-label">
                    <?= __('admin.tenants.currency') ?>
                </label>
                <select id="tenant_currency" name="currency" class="vb-select">
                    <?php foreach ($currencies as $code => $label): ?>
                        <option value="<?= $code ?>" <?= $code === 'EUR' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Brand Color -->
        <div class="vb-form-group" x-data="colorSync">
            <label class="vb-label vb-icon-label">
                <i data-lucide="palette"></i>
                <?= __('admin.tenants.brand_color') ?>
            </label>
            <div class="vb-color-field">
                <input type="color" x-ref="colorPicker" value="#2563EB" class="vb-color-input"
                       @input="onPickerChange">
                <input type="text" x-ref="colorText" name="brand_color" value="#2563EB" class="vb-input"
                       maxlength="7" placeholder="#2563EB" @input="onTextChange">
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
