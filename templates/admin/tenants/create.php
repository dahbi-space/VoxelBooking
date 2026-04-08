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
 * Variables: $user, $version, $csrfToken, $flash, $pageTitle, $activePage,
 *            $old (old input on validation failure), $fieldErrors (per-field errors)
 */
$activePage = 'tenants';

$timezones = get_supported_timezones();
$currencies = get_supported_currencies();

// Old input (repopulates form on validation failure)
$old = $old ?? [];
$fieldErrors = $fieldErrors ?? [];

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

/** Helper: escape old input value for HTML attribute. */
function oldVal(array $old, string $key, string $default = ''): string {
    return htmlspecialchars($old[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

/** Helper: return 'is-invalid' if field has server-side error. */
function fieldClass(array $errors, string $field): string {
    return isset($errors[$field]) ? ' is-invalid' : '';
}

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
    <?php include __DIR__ . '/../../partials/alert.php'; ?>
<?php endif; ?>

<div class="vb-card vb-fade-in-up">
    <form method="POST" action="/admin/tenants/create" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="vb-form-group">
            <label for="tenant_name" class="vb-label"><?= __('admin.tenants.name') ?> <span class="vb-required">*</span></label>
            <input type="text" id="tenant_name" name="name" class="vb-input<?= fieldClass($fieldErrors, 'name') ?>" required
                   value="<?= oldVal($old, 'name') ?>"
                   placeholder="Acme Hair Studio">
            <?php if (isset($fieldErrors['name'])): ?>
                <div class="vb-form-error" role="alert"><?= htmlspecialchars($fieldErrors['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <div class="vb-form-group">
            <label for="tenant_slug" class="vb-label"><?= __('admin.tenants.slug') ?></label>
            <input type="text" id="tenant_slug" name="slug" class="vb-input<?= fieldClass($fieldErrors, 'slug') ?>"
                   value="<?= oldVal($old, 'slug') ?>"
                   placeholder="acme-hair-studio">
            <?php if (isset($fieldErrors['slug'])): ?>
                <div class="vb-form-error" role="alert"><?= htmlspecialchars($fieldErrors['slug'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
                <span class="vb-hint"><?= __('admin.tenants.slug_help') ?></span>
            <?php endif; ?>
        </div>

        <div class="vb-form-group">
            <label for="tenant_email" class="vb-label"><?= __('admin.tenants.email') ?> <span class="vb-required">*</span></label>
            <input type="email" id="tenant_email" name="email" class="vb-input<?= fieldClass($fieldErrors, 'email') ?>" required
                   value="<?= oldVal($old, 'email') ?>"
                   placeholder="hello@example.com">
            <?php if (isset($fieldErrors['email'])): ?>
                <div class="vb-form-error" role="alert"><?= htmlspecialchars($fieldErrors['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <!-- Brand Color -->
        <div class="vb-form-group" x-data="colorSync">
            <label class="vb-label vb-icon-label">
                <i data-lucide="palette"></i>
                <?= __('admin.tenants.brand_color') ?>
            </label>
            <div class="vb-color-field">
                <input type="color" x-ref="colorPicker" value="<?= oldVal($old, 'brand_color', '#2563EB') ?>" class="vb-color-input"
                       @input="onPickerChange">
                <input type="text" x-ref="colorText" name="brand_color" value="<?= oldVal($old, 'brand_color', '#2563EB') ?>" class="vb-input"
                       maxlength="7" placeholder="#2563EB" @input="onTextChange">
            </div>
        </div>

        <div class="vb-section-divider"></div>

        <!-- Booking Pattern Cards -->
        <?php $selectedPattern = $old['booking_pattern'] ?? 'timeslot'; ?>
        <div class="vb-form-group" x-data="patternCards">
            <label class="vb-label"><?= __('admin.tenants.pattern') ?></label>
            <div class="vb-pattern-cards">
                <?php foreach ($patterns as $value => $info): ?>
                <label class="vb-pattern-card" @click="select('<?= $value ?>')">
                    <input type="radio" name="booking_pattern" value="<?= $value ?>"
                           <?= $value === $selectedPattern ? 'checked' : '' ?>>
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
        <?php $selectedTz = $old['timezone'] ?? 'UTC'; ?>
        <?php $selectedCurrency = $old['currency'] ?? 'EUR'; ?>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label for="tenant_timezone" class="vb-label vb-icon-label">
                    <i data-lucide="globe"></i>
                    <?= __('admin.tenants.timezone') ?>
                </label>
                <select id="tenant_timezone" name="timezone" class="vb-select">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= htmlspecialchars($tz, ENT_QUOTES, 'UTF-8') ?>" <?= $tz === $selectedTz ? 'selected' : '' ?>>
                            <?= htmlspecialchars(format_timezone($tz), ENT_QUOTES, 'UTF-8') ?>
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
                        <option value="<?= $code ?>" <?= $code === $selectedCurrency ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>


        <!-- Owner Access (Optional) -->
        <?php $ownerEnabled = ($old['create_owner'] ?? '0') === '1'; ?>
        <div class="vb-owner-section" x-data="ownerSetup" data-smtp-configured="<?= \App\Engine\Mailer::isConfigured() ? '1' : '0' ?>"
             <?php if ($ownerEnabled): ?>data-initially-enabled="1"<?php endif; ?>>
            <div class="vb-section-divider"></div>

            <div class="vb-form-group">
                <div class="vb-toggle-row">
                    <input type="checkbox" id="create_owner_toggle"
                           :checked="enabled" @change="onToggleEnabled" class="vb-checkbox">
                    <input type="hidden" name="create_owner" :value="ownerFormValue">
                    <label for="create_owner_toggle" class="vb-toggle-label">
                        <span class="vb-label vb-mb-0">
                            <i data-lucide="user-plus" class="vb-icon-sm"></i>
                            <?= __('admin.tenants.owner_section_title') ?>
                        </span>
                        <span class="vb-hint"><?= __('admin.tenants.owner_section_desc') ?></span>
                    </label>
                </div>
            </div>

            <div x-show="enabled" x-transition.duration.200ms style="display: none;">
                <div class="vb-form-group">
                    <label for="owner_name" class="vb-label"><?= __('admin.tenants.owner_name') ?> <span class="vb-required">*</span></label>
                    <input type="text" id="owner_name" name="owner_name" class="vb-input<?= fieldClass($fieldErrors, 'owner_name') ?>"
                           value="<?= oldVal($old, 'owner_name') ?>"
                           placeholder="Jane Doe" :required="enabled">
                    <?php if (isset($fieldErrors['owner_name'])): ?>
                        <div class="vb-form-error" role="alert"><?= htmlspecialchars($fieldErrors['owner_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="vb-form-group">
                    <label for="owner_email" class="vb-label"><?= __('admin.tenants.owner_email') ?> <span class="vb-required">*</span></label>
                    <input type="email" id="owner_email" name="owner_email" class="vb-input<?= fieldClass($fieldErrors, 'owner_email') ?>"
                           x-ref="ownerEmail"
                           value="<?= oldVal($old, 'owner_email') ?>"
                           placeholder="owner@example.com" :required="enabled">
                    <?php if (isset($fieldErrors['owner_email'])): ?>
                        <div class="vb-form-error" role="alert"><?= htmlspecialchars($fieldErrors['owner_email'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="vb-form-group">
                    <label for="owner_password" class="vb-label"><?= __('admin.tenants.owner_password') ?></label>
                    <div class="vb-password-field">
                        <input type="password" id="owner_password" name="owner_password" class="vb-input"
                               x-ref="ownerPassword" autocomplete="new-password">
                        <button type="button" class="vb-btn vb-btn-ghost vb-btn-sm" @click="togglePasswordVisibility"
                                title="<?= __('admin.tenants.owner_toggle_visibility') ?>">
                            <i data-lucide="eye" x-show="!showPassword"></i>
                            <i data-lucide="eye-off" x-show="showPassword"></i>
                        </button>
                        <button type="button" class="vb-btn vb-btn-ghost vb-btn-sm" @click="generatePassword()"
                                title="<?= __('admin.tenants.owner_generate_password') ?>">
                            <i data-lucide="refresh-cw"></i>
                        </button>
                    </div>
                </div>

                <div class="vb-form-group">
                    <div class="vb-toggle-row">
                        <input type="checkbox" id="send_owner_email_toggle" name="send_owner_email" value="1"
                               :checked="sendEmail"
                               @change="onToggleSendEmail"
                               :disabled="smtpNotConfigured"
                               class="vb-checkbox">
                        <label for="send_owner_email_toggle" class="vb-toggle-label">
                            <span class="vb-label vb-mb-0">
                                <i data-lucide="mail" class="vb-icon-sm"></i>
                                <?= __('admin.tenants.owner_send_email') ?>
                            </span>
                            <?php if (!\App\Engine\Mailer::isConfigured()): ?>
                                <span class="vb-hint vb-hint-warning">
                                    <i data-lucide="alert-triangle" class="vb-icon-xs"></i>
                                    <?= __('admin.tenants.owner_smtp_hint') ?>
                                </span>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>
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
