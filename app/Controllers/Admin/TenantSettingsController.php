<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\FormState;
use App\Engine\AuditLog;
use App\Engine\BrandColorHelper;
use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Ulid;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Engine\ImageUpload;
use App\Models\Tenant;

/**
 * Tenant settings controller (tenant-scoped, all patterns).
 *
 * Access: operators + business owners (Auth::canManageTenant()).
 * Managers are forbidden (403).
 *
 * Five tabs: General, Branding, Booking Rules, Privacy, Notifications.
 */
final class TenantSettingsController
{
    // ── General ──

    public function general(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        return $this->render('admin.tenants.settings.general', $tenantId, $tenant, 'general', [
            'localeOptions'   => \App\Engine\Locale::localeOptions(),
            'timezones'       => get_supported_timezones(),
            'currencyOptions' => get_supported_currencies(),
        ]);
    }

    public function saveGeneral(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $name     = trim($request->string('name'));
        $email    = trim($request->string('email'));
        $phone    = trim($request->string('phone')) ?: null;
        $slugRaw  = trim($request->string('slug'));
        $slug     = $slugRaw !== '' ? $slugRaw : null;
        $timezone   = trim($request->string('timezone')) ?: 'UTC';
        if (!in_array($timezone, get_supported_timezones(), true)) {
            $timezone = $tenant['timezone'] ?? 'UTC';
        }
        $locale     = trim($request->string('locale')) ?: 'en';
        if (!\App\Engine\Locale::isSupported($locale)) {
            $locale = $tenant['locale'] ?? 'en';
        }
        $currency   = strtoupper(trim($request->string('currency'))) ?: 'EUR';
        if (!array_key_exists($currency, get_supported_currencies())) {
            $currency = $tenant['currency'] ?? 'EUR';
        }
        $weekStartRaw = $request->string('week_start');
        $weekStart  = $weekStartRaw !== '' ? max(0, min(6, (int) $weekStartRaw)) : null;
        $timeFormat = trim($request->string('time_format')) ?: null;
        if ($timeFormat !== null && !in_array($timeFormat, ['12h', '24h'], true)) {
            $timeFormat = null;
        }
        $dateFormat = trim($request->string('date_format')) ?: null;
        if ($dateFormat !== null && !in_array($dateFormat, ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd.m.Y'], true)) {
            $dateFormat = null;
        }
        $numberFormat = trim($request->string('number_format')) ?: null;
        if ($numberFormat !== null && !in_array($numberFormat, ['period', 'comma', 'space'], true)) {
            $numberFormat = null;
        }

        $errors = [];
        if ($name === '') {
            $errors['name'] = __('admin.tenant_settings.error_name_required');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('admin.tenant_settings.error_email_invalid');
        }
        if ($slug !== null && $slug !== '' && !preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $errors['slug'] = __('admin.tenant_settings.error_slug_invalid');
        }
        if ($slug !== null && $slug !== '' && empty($errors['slug']) && Tenant::slugExists($slug, excludeId: $tenantId)) {
            $errors['slug'] = __('admin.tenant_settings.error_slug_taken');
        }

        if (!empty($errors)) {
            FormState::flash([
                'name' => $name, 'email' => $email, 'phone' => $phone ?? '',
                'slug' => $slug ?? '', 'timezone' => $timezone, 'locale' => $locale, 'currency' => $currency,
            ], $errors);

            $firstError = reset($errors);
            FormState::toast('error', $firstError);
            return Response::redirect("/admin/tenants/{$tenantId}/settings");
        }

        $data = [
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'slug'          => $slug !== null && $slug !== '' ? $slug : ($tenant['slug'] ?? null),
            'timezone'      => $timezone,
            'locale'        => $locale,
            'currency'      => $currency,
            'week_start'    => $weekStart,
            'time_format'   => $timeFormat,
            'date_format'   => $dateFormat,
            'number_format' => $numberFormat,
        ];

        $this->saveTenant($tenantId, $data, $tenant, 'general');
        return Response::redirect("/admin/tenants/{$tenantId}/settings");
    }

    // ── Branding ──

    public function branding(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        return $this->render('admin.tenants.settings.branding', $tenantId, $tenant, 'branding');
    }

    public function saveBranding(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $brandColor    = trim($request->string('brand_color')) ?: '#2563EB';
        $heading       = trim($request->string('booking_page_heading')) ?: null;
        $description   = trim($request->string('booking_page_description')) ?: null;

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $brandColor)) {
            $brandColor = '#2563EB';
        }

        // Auto-derive text color from brand color via WCAG luminance
        $brandColorTxt = BrandColorHelper::derive($brandColor)['brand_text'];

        $data = [
            'brand_color'              => $brandColor,
            'brand_color_text'         => $brandColorTxt,
            'show_powered_by'          => $request->string('show_powered_by') === '1' ? 1 : 0,
            'booking_page_heading'     => $heading,
            'booking_page_description' => $description,
        ];

        // Handle logo upload / removal
        $oldLogoPath = $tenant['logo_path'] ?? null;
        $logoPath    = $oldLogoPath;
        $newLogoPath = null; // track newly uploaded file for rollback
        $removeLogo  = ($request->string('remove_logo') === '1');

        if (!empty($_FILES['logo']['tmp_name'])) {
            // Upload WITHOUT deleting the old file (pass null for $oldPath)
            $upload = ImageUpload::store('logo', $_FILES['logo'], $tenant['slug']);
            if ($upload['error']) {
                FormState::toast('error', $upload['error']);
                FormState::flashInput([
                    'brand_color'              => $brandColor,
                    'booking_page_heading'     => $heading,
                    'booking_page_description' => $description,
                ]);
                return Response::redirect("/admin/tenants/{$tenantId}/settings/branding");
            }
            if ($upload['path']) {
                $newLogoPath = $upload['path'];
                $logoPath    = $newLogoPath;
            }
        } elseif ($removeLogo && $logoPath) {
            // Defer deletion — just set path to null for now
            $logoPath = null;
        }
        $data['logo_path'] = $logoPath;

        // Persist to DB, then clean up files
        try {
            $this->saveTenant($tenantId, $data, $tenant, 'branding');
        } catch (\Throwable $e) {
            // DB write failed — roll back the new file if we uploaded one
            if ($newLogoPath) {
                ImageUpload::delete($newLogoPath);
            }
            FormState::flashInput([
                'brand_color'              => $brandColor,
                'booking_page_heading'     => $heading,
                'booking_page_description' => $description,
            ]);
            FormState::toast('error', __('admin.common.error_generic'));
            return Response::redirect("/admin/tenants/{$tenantId}/settings/branding");
        }

        // DB write succeeded — now safely clean up the old file
        if ($newLogoPath && $oldLogoPath && $oldLogoPath !== $newLogoPath) {
            ImageUpload::delete($oldLogoPath);
        } elseif ($removeLogo && $oldLogoPath && $logoPath === null) {
            ImageUpload::delete($oldLogoPath);
        }

        return Response::redirect("/admin/tenants/{$tenantId}/settings/branding");
    }

    // ── Booking Page (all patterns) ──

    public function bookingPage(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        return $this->render('admin.tenants.settings.bookingpage', $tenantId, $tenant, 'bookingpage');
    }

    public function saveBookingPage(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $data = [
            'confirmation_message'       => trim($request->string('confirmation_message')) ?: null,
            'cancellation_policy'        => trim($request->string('cancellation_policy')) ?: null,
            'require_phone'              => $request->string('require_phone') === '1' ? 1 : 0,
            'booking_requires_approval'  => $request->string('booking_requires_approval') === '1' ? 1 : 0,
            'allow_cancellation'         => $request->string('allow_cancellation') === '1' ? 1 : 0,
            'cancellation_hours_before'  => max(0, (int) $request->string('cancellation_hours_before')),
            'allow_rescheduling'         => $request->string('allow_rescheduling') === '1' ? 1 : 0,
            'rescheduling_hours_before'  => max(0, (int) $request->string('rescheduling_hours_before')),
        ];

        $this->saveTenant($tenantId, $data, $tenant, 'bookingpage');
        return Response::redirect("/admin/tenants/{$tenantId}/settings/bookingpage");
    }

    // ── Booking Rules (timeslot + resource — Phase R) ──

    public function booking(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        // Phase R: timeslot + resource only. Capacity/event gated until their phases.
        if (!in_array($tenant['booking_pattern'] ?? '', ['timeslot', 'resource'], true)) {
            return $this->forbidden($request);
        }

        return $this->render('admin.tenants.settings.booking', $tenantId, $tenant, 'booking');
    }

    public function saveBooking(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        // Phase R: timeslot + resource only. Capacity/event gated until their phases.
        if (!in_array($tenant['booking_pattern'] ?? '', ['timeslot', 'resource'], true)) {
            return $this->forbidden($request);
        }

        // Shared booking constraints (timeslot + resource)
        $data = [
            'min_advance_hours'                 => max(0, (int) $request->string('min_advance_hours')),
            'max_advance_days'                  => max(1, (int) $request->string('max_advance_days')),
            'max_bookings_per_customer_per_day'  => max(0, (int) $request->string('max_bookings_per_customer_per_day')),
        ];

        // Timeslot-specific fields — only process when present (template hides them for other patterns)
        if (($tenant['booking_pattern'] ?? '') === 'timeslot') {
            $data['slot_duration_minutes'] = max(5, (int) $request->string('slot_duration_minutes'));
            $data['buffer_minutes']        = max(0, (int) $request->string('buffer_minutes'));
        }

        $this->saveTenant($tenantId, $data, $tenant, 'booking');
        return Response::redirect("/admin/tenants/{$tenantId}/settings/booking");
    }

    // ── Privacy ──

    public function privacy(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        return $this->render('admin.tenants.settings.privacy', $tenantId, $tenant, 'privacy');
    }

    public function savePrivacy(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $data = [
            'requires_consent'     => $request->string('requires_consent') === '1' ? 1 : 0,
            'privacy_policy_url'   => trim($request->string('privacy_policy_url')) ?: null,
            'consent_text'         => trim($request->string('consent_text')) ?: null,
            'data_retention_months'=> max(1, (int) $request->string('data_retention_months')),
        ];

        $this->saveTenant($tenantId, $data, $tenant, 'privacy');
        return Response::redirect("/admin/tenants/{$tenantId}/settings/privacy");
    }

    // ── Notifications ──

    public function notifications(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        return $this->render('admin.tenants.settings.notifications', $tenantId, $tenant, 'notifications');
    }

    public function saveNotifications(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $notifEmail = trim($request->string('notification_email')) ?: null;

        $errors = [];
        if ($notifEmail !== null && !filter_var($notifEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['notification_email'] = __('admin.tenant_settings.error_notif_email_invalid');
        }

        if (!empty($errors)) {
            FormState::flash([
                'notification_email'     => $notifEmail ?? '',
                'notify_on_booking'      => $request->string('notify_on_booking'),
                'notify_on_cancellation' => $request->string('notify_on_cancellation'),
                'send_reminders'         => $request->string('send_reminders'),
                'reminder_hours_before'  => $request->string('reminder_hours_before'),
            ], $errors);

            $firstError = reset($errors);
            FormState::toast('error', $firstError);
            return Response::redirect("/admin/tenants/{$tenantId}/settings/notifications");
        }

        $data = [
            'notification_email'      => $notifEmail,
            'notify_on_booking'       => $request->string('notify_on_booking') === '1' ? 1 : 0,
            'notify_on_cancellation'  => $request->string('notify_on_cancellation') === '1' ? 1 : 0,
            'send_reminders'          => $request->string('send_reminders') === '1' ? 1 : 0,
            'reminder_hours_before'   => max(1, (int) $request->string('reminder_hours_before')),
        ];

        $this->saveTenant($tenantId, $data, $tenant, 'notifications');
        return Response::redirect("/admin/tenants/{$tenantId}/settings/notifications");
    }

    // ── Helpers ──

    private function canAccess(string $tenantId): bool
    {
        return Auth::canAccessTenant($tenantId) && Auth::canManageTenant();
    }

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query('SELECT * FROM `tenants` WHERE `id` = ? LIMIT 1', [$tenantId]);
        return $rows[0] ?? null;
    }

    /**
     * Update tenant fields, compute diff, log audit, flash success.
     */
    private function saveTenant(string $tenantId, array $data, array $tenant, string $tab): void
    {
        $changes = [];
        foreach ($data as $k => $v) {
            $old = $tenant[$k] ?? null;
            if (is_int($v)) {
                $old = (int) $old;
            }
            if ($old !== $v) {
                $changes[$k] = ['old' => $tenant[$k] ?? null, 'new' => $v];
            }
        }

        if (empty($changes)) {
            FormState::toast('info', __('admin.tenant_settings.no_changes'));
            return;
        }

        $sets = [];
        $params = [];
        foreach ($data as $k => $v) {
            if (array_key_exists($k, $changes)) {
                $sets[] = "`{$k}` = ?";
                $params[] = $v;
            }
        }
        $params[] = $tenantId;

        Database::execute(
            'UPDATE `tenants` SET ' . implode(', ', $sets) . ' WHERE `id` = ?',
            $params
        );

        AuditLog::log('tenant.settings_updated', 'tenant', $tenantId, [
            'tab'     => $tab,
            'changes' => $changes,
        ]);

        FormState::toast('success', __('admin.tenant_settings.saved'));
    }

    private function render(string $template, string $tenantId, array $tenant, string $activeTab, array $extraData = []): Response
    {
        return View::response($template, array_merge([
            'user'          => Auth::user(),
            'version'       => Version::get(),
            'pageTitle'     => __('admin.tenant_settings.title'),
            'documentTitle' => __('admin.tenant_settings.title') . ' — ' . $tenant['name'],
            'activePage'    => 'settings',
            'activeTab'     => $activeTab,
            'csrfToken'     => CsrfMiddleware::generateToken(),
            'tenantId'      => $tenantId,
            'tenant'        => $tenant,
            'flash'         => FormState::getToast(),
        ], $extraData));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Owner or operator access required.',
            ], 403);
        }

        return View::response('admin.errors.403', [
            'user'      => Auth::user(),
            'version'   => Version::get(),
            'pageTitle' => '403',
            'csrfToken' => CsrfMiddleware::generateToken(),
        ], 403);
    }


    // ── Email Templates ──

    public function emails(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $templates = Database::query(
            'SELECT * FROM `tenant_email_templates` WHERE `tenant_id` = ? ORDER BY `type`',
            [$tenantId]
        );

        return View::response('admin.tenants.settings.emails', [
            'user'          => Auth::user(),
            'version'       => Version::get(),
            'pageTitle'     => __('admin.tenant_settings.title'),
            'documentTitle' => __('admin.tenant_settings.title') . ' — ' . $tenant['name'],
            'activePage'    => 'settings',
            'activeTab'     => 'emails',
            'csrfToken'     => CsrfMiddleware::generateToken(),
            'tenantId'      => $tenantId,
            'tenant'        => $tenant,
            'templates'     => $templates,
            'flash'         => FormState::getToast(),
        ]);
    }

    // ── Embed Settings ──

    public function embed(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        return $this->render('admin.tenants.settings.embed', $tenantId, $tenant, 'embed');
    }

    public function saveEmbed(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $position = trim($request->string('embed_button_position'));
        if (!in_array($position, ['bottom-right', 'bottom-left'], true)) {
            $position = 'bottom-right';
        }

        $label = trim($request->string('embed_button_label')) ?: 'Book Now';
        if (mb_strlen($label) > 50) {
            $label = mb_substr($label, 0, 50);
        }

        // Parse allowed domains: one per line, strip protocol, trim
        $domainsRaw = trim($request->string('allowed_embed_domains'));
        $domains = null;
        if ($domainsRaw !== '') {
            $lines = array_filter(array_map(function (string $line): string {
                $line = trim($line);
                // Strip protocol if present
                $line = preg_replace('#^https?://#', '', $line);
                // Strip trailing slash
                return rtrim($line, '/');
            }, explode("\n", $domainsRaw)));
            $domains = !empty($lines) ? implode(',', $lines) : null;
        }

        $data = [
            'embed_button_position'  => $position,
            'embed_button_label'     => $label,
            'allowed_embed_domains'  => $domains,
        ];

        $this->saveTenant($tenantId, $data, $tenant, 'embed');
        return Response::redirect("/admin/tenants/{$tenantId}/settings/embed");
    }

    public function saveEmails(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        // Only customer-facing types are tenant-customizable.
        // staff_notification is operational with fixed copy (not editable).
        $types = ['confirmation', 'reminder', 'cancellation', 'reschedule_confirmation', 'approval_request', 'approval_confirmed'];
        $fields = ['subject', 'heading', 'body_intro', 'body_outro', 'cta_label'];

        foreach ($types as $type) {
            $isEnabled = (int) $request->string("{$type}_is_enabled");

            // Check if any field has a value
            $hasData = false;
            $fieldValues = [];
            foreach ($fields as $field) {
                $val = trim($request->string("{$type}_{$field}"));
                $fieldValues[$field] = $val;
                if ($val !== '') {
                    $hasData = true;
                }
            }

            // Load existing row
            $existing = Database::query(
                'SELECT `id` FROM `tenant_email_templates` WHERE `tenant_id` = ? AND `type` = ? LIMIT 1',
                [$tenantId, $type]
            );

            if ($existing) {
                // Update existing
                Database::execute(
                    'UPDATE `tenant_email_templates`
                     SET `subject` = ?, `heading` = ?, `body_intro` = ?, `body_outro` = ?,
                         `cta_label` = ?, `is_enabled` = ?
                     WHERE `id` = ?',
                    [
                        $fieldValues['subject'] ?: null,
                        $fieldValues['heading'] ?: null,
                        $fieldValues['body_intro'] ?: null,
                        $fieldValues['body_outro'] ?: null,
                        $fieldValues['cta_label'] ?: null,
                        $isEnabled,
                        $existing[0]['id'],
                    ]
                );
            } elseif ($hasData || $isEnabled === 0) {
                // Insert new row only if there's data or the type is disabled
                $id = Ulid::generate();
                Database::execute(
                    'INSERT INTO `tenant_email_templates`
                     (`id`, `tenant_id`, `type`, `subject`, `heading`, `body_intro`, `body_outro`, `cta_label`, `is_enabled`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        (string) $id,
                        $tenantId,
                        $type,
                        $fieldValues['subject'] ?: null,
                        $fieldValues['heading'] ?: null,
                        $fieldValues['body_intro'] ?: null,
                        $fieldValues['body_outro'] ?: null,
                        $fieldValues['cta_label'] ?: null,
                        $isEnabled,
                    ]
                );
            }
        }

        AuditLog::log('tenant.emails_updated', 'tenant', $tenantId, ['types' => $types], $tenantId);

        FormState::toast('success', __('admin.tenant_settings.emails_saved'));
        return Response::redirect("/admin/tenants/{$tenantId}/settings/emails");
    }
}

