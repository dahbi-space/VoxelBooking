<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Ulid;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Tenant settings controller (tenant-scoped, all patterns).
 *
 * Access: operators + business owners (Auth::canManageTenant()).
 * Managers are forbidden (403).
 *
 * Five tabs: General, Branding, Booking Rules (timeslot only), Privacy, Notifications.
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

        return $this->render('admin.tenants.settings.general', $tenantId, $tenant, 'general');
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
        $timezone = trim($request->string('timezone')) ?: 'UTC';
        $locale   = trim($request->string('locale')) ?: 'en';
        $currency = trim($request->string('currency')) ?: 'EUR';

        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.tenant_settings.error_name_required');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('admin.tenant_settings.error_email_invalid');
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            $this->setOldInput([
                'name' => $name, 'email' => $email, 'phone' => $phone ?? '',
                'timezone' => $timezone, 'locale' => $locale, 'currency' => $currency,
            ]);
            return Response::redirect("/admin/tenants/{$tenantId}/settings");
        }

        $data = [
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phone,
            'timezone' => $timezone,
            'locale'   => $locale,
            'currency' => $currency,
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
        $brandColorTxt = trim($request->string('brand_color_text')) ?: '#FFFFFF';
        $heading       = trim($request->string('booking_page_heading')) ?: null;
        $description   = trim($request->string('booking_page_description')) ?: null;

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $brandColor)) {
            $brandColor = '#2563EB';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $brandColorTxt)) {
            $brandColorTxt = '#FFFFFF';
        }

        $data = [
            'brand_color'              => $brandColor,
            'brand_color_text'         => $brandColorTxt,
            'booking_page_heading'     => $heading,
            'booking_page_description' => $description,
        ];

        $this->saveTenant($tenantId, $data, $tenant, 'branding');
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

    // ── Booking Rules (timeslot pattern only) ──

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

        if (($tenant['booking_pattern'] ?? '') !== 'timeslot') {
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

        if (($tenant['booking_pattern'] ?? '') !== 'timeslot') {
            return $this->forbidden($request);
        }

        $data = [
            'slot_duration_minutes'             => max(5, (int) $request->string('slot_duration_minutes')),
            'buffer_minutes'                    => max(0, (int) $request->string('buffer_minutes')),
            'min_advance_hours'                 => max(0, (int) $request->string('min_advance_hours')),
            'max_advance_days'                  => max(1, (int) $request->string('max_advance_days')),
            'max_bookings_per_customer_per_day'  => max(0, (int) $request->string('max_bookings_per_customer_per_day')),
        ];

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
            $errors[] = __('admin.tenant_settings.error_notif_email_invalid');
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            $this->setOldInput([
                'notification_email'     => $notifEmail ?? '',
                'notify_on_booking'      => $request->string('notify_on_booking'),
                'notify_on_cancellation' => $request->string('notify_on_cancellation'),
                'send_reminders'         => $request->string('send_reminders'),
                'reminder_hours_before'  => $request->string('reminder_hours_before'),
            ]);
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
            $this->setFlash('info', __('admin.tenant_settings.no_changes'));
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

        $this->setFlash('success', __('admin.tenant_settings.saved'));
    }

    private function render(string $template, string $tenantId, array $tenant, string $activeTab): Response
    {
        return View::response($template, [
            'user'          => Auth::user(),
            'version'       => Version::get(),
            'pageTitle'     => __('admin.tenant_settings.title'),
            'documentTitle' => __('admin.tenant_settings.title') . ' — ' . $tenant['name'],
            'activePage'    => 'settings',
            'activeTab'     => $activeTab,
            'csrfToken'     => CsrfMiddleware::generateToken(),
            'tenantId'      => $tenantId,
            'tenant'        => $tenant,
            'old'           => $this->getOldInput(),
            'flash'         => $this->flash(),
        ]);
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

    private function setFlash(string $type, string $message): void
    {
        Auth::startSession();
        $_SESSION['settings_flash'] = ['type' => $type, 'message' => $message];
    }

    private function flash(): ?array
    {
        $flash = $_SESSION['settings_flash'] ?? null;
        unset($_SESSION['settings_flash']);
        return $flash;
    }

    private function setOldInput(array $data): void
    {
        Auth::startSession();
        $_SESSION['settings_old_input'] = $data;
    }

    private function getOldInput(): ?array
    {
        $old = $_SESSION['settings_old_input'] ?? null;
        unset($_SESSION['settings_old_input']);
        return $old;
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
            'old'           => $this->getOldInput(),
            'flash'         => $this->flash(),
        ]);
    }

    public function saveEmails(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $types = ['confirmation', 'reminder', 'cancellation', 'reschedule_confirmation', 'staff_notification', 'approval_request', 'approval_confirmed'];
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

        $this->setFlash('success', __('admin.tenant_settings.emails_saved'));
        return Response::redirect("/admin/tenants/{$tenantId}/settings/emails");
    }
}

