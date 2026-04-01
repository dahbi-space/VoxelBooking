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
 * Event management controller (tenant-scoped, event-pattern only).
 *
 * Provides CRUD for events with one-off / recurring support,
 * waitlist config, and participant tracking.
 * Access: operators + business owners (Auth::canManageTenant()).
 */
final class EventsController
{

    public function index(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (($tenant['booking_pattern'] ?? '') !== 'event') {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $events = Database::query(
            'SELECT e.*, (
                SELECT COALESCE(SUM(b.party_size), 0)
                FROM bookings b
                WHERE b.event_id = e.id AND b.booking_pattern = \'event\'
                AND b.status IN (\'confirmed\', \'rescheduled\')
             ) AS booked_count,
             (
                SELECT COALESCE(SUM(b.party_size), 0)
                FROM bookings b
                WHERE b.event_id = e.id AND b.booking_pattern = \'event\'
                AND b.status = \'waitlisted\'
             ) AS waitlist_count
             FROM `events` e
             WHERE e.`tenant_id` = ?
             ORDER BY e.`start_datetime` ASC',
            [$tenantId]
        );

        return $this->render('admin.tenants.events.index', __('admin.events.title'), [
            'documentTitle' => __('admin.events.title'),
            'tenant'        => $tenant,
            'tenantId'      => $tenantId,
            'events'        => $events,
            'flash'         => $this->flash(),
        ], $tenantId);
    }

    public function create(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (($tenant['booking_pattern'] ?? '') !== 'event') {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        return $this->render('admin.tenants.events.create', __('admin.events.create_title'), [
            'documentTitle' => __('admin.events.create_title'),
            'tenant'        => $tenant,
            'tenantId'      => $tenantId,
            'flash'         => $this->flash(),
            'old'           => $_SESSION['_old_input'] ?? [],
        ], $tenantId);
    }

    public function store(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || ($tenant['booking_pattern'] ?? '') !== 'event') {
            return Response::redirect('/admin/tenants');
        }

        // Store old input for re-display on validation failure
        Auth::startSession();
        $_SESSION['_old_input'] = [
            'name'             => $request->string('name'),
            'description'      => $request->string('description'),
            'location'         => $request->string('location'),
            'price'            => $request->string('price'),
            'max_participants' => $request->string('max_participants'),
            'start_date'       => $request->string('start_date'),
            'start_time'       => $request->string('start_time'),
            'end_date'         => $request->string('end_date'),
            'end_time'         => $request->string('end_time'),
            'is_recurring'     => $request->string('is_recurring'),
            'frequency'        => $request->string('frequency'),
            'count'            => $request->string('count'),
            'exception_dates'  => $request->string('exception_dates'),
            'allow_waitlist'   => $request->string('allow_waitlist'),
            'waitlist_max'     => $request->string('waitlist_max'),
        ];

        // Validation
        $name = trim($request->string('name'));
        if ($name === '') {
            $this->setFlash('error', __('admin.events.error_name_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/events/create");
        }

        $startDate = trim($request->string('start_date'));
        $startTime = trim($request->string('start_time'));
        $endDate   = trim($request->string('end_date'));
        $endTime   = trim($request->string('end_time'));

        if (!$startDate || !$startTime || !$endDate || !$endTime) {
            $this->setFlash('error', __('admin.events.error_date_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/events/create");
        }

        $startDt = $startDate . ' ' . $startTime . ':00';
        $endDt   = $endDate . ' ' . $endTime . ':00';

        if ($endDt <= $startDt) {
            $this->setFlash('error', __('admin.events.error_end_before_start'));
            return Response::redirect("/admin/tenants/{$tenantId}/events/create");
        }

        $maxParticipants = max(1, (int) $request->string('max_participants'));
        $price = $request->string('price') !== '' ? number_format((float) $request->string('price'), 2, '.', '') : null;

        // Recurrence
        $isRecurring = $request->string('is_recurring') === '1';
        $rrule = null;
        if ($isRecurring) {
            $freq = strtoupper(trim($request->string('frequency')));
            $count = max(1, (int) $request->string('count'));
            if (in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
                $rrule = "FREQ={$freq};COUNT={$count}";
            }
        }

        // Exception dates
        $exceptionsRaw = trim($request->string('exception_dates'));
        $exceptionDates = null;
        if ($exceptionsRaw !== '') {
            $dates = array_filter(array_map('trim', preg_split('/[\r\n]+/', $exceptionsRaw)));
            $valid = array_filter($dates, fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d));
            if (!empty($valid)) {
                $exceptionDates = json_encode(array_values($valid));
            }
        }

        // Waitlist
        $allowWaitlist = $request->string('allow_waitlist') === '1' ? 1 : 0;
        $waitlistMax = max(0, (int) $request->string('waitlist_max'));

        $eventId = Ulid::generate();

        Database::execute(
            'INSERT INTO `events` (`id`, `tenant_id`, `name`, `description`, `location`, `price`,
             `max_participants`, `start_datetime`, `end_datetime`, `is_recurring`, `rrule`,
             `exception_dates`, `allow_waitlist`, `waitlist_max`, `is_active`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                $eventId, $tenantId, $name,
                trim($request->string('description')) ?: null,
                trim($request->string('location')) ?: null,
                $price, $maxParticipants, $startDt, $endDt,
                $isRecurring ? 1 : 0, $rrule, $exceptionDates,
                $allowWaitlist, $waitlistMax,
            ]
        );

        AuditLog::log('event.created', 'event', $eventId, [
            'name' => $name,
            'start_datetime' => $startDt,
            'is_recurring' => $isRecurring,
        ], $tenantId);

        unset($_SESSION['_old_input']);
        $this->setFlash('success', __('admin.events.flash_created'));
        return Response::redirect("/admin/tenants/{$tenantId}/events");
    }

    public function edit(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $eventId = $request->getAttribute('id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || ($tenant['booking_pattern'] ?? '') !== 'event') {
            return Response::redirect('/admin/tenants');
        }

        $events = Database::query(
            'SELECT * FROM `events` WHERE `id` = ? AND `tenant_id` = ?',
            [$eventId, $tenantId]
        );
        if (empty($events)) {
            return Response::redirect("/admin/tenants/{$tenantId}/events");
        }

        return $this->render('admin.tenants.events.edit', __('admin.events.edit_title'), [
            'documentTitle' => __('admin.events.edit_title'),
            'tenant'        => $tenant,
            'tenantId'      => $tenantId,
            'event'         => $events[0],
            'flash'         => $this->flash(),
            'old'           => $_SESSION['_old_input'] ?? [],
        ], $tenantId);
    }

    public function update(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $eventId = $request->getAttribute('id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || ($tenant['booking_pattern'] ?? '') !== 'event') {
            return Response::redirect('/admin/tenants');
        }

        // Validate
        $name = trim($request->string('name'));
        if ($name === '') {
            $this->setFlash('error', __('admin.events.error_name_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/events/{$eventId}/edit");
        }

        $startDate = trim($request->string('start_date'));
        $startTime = trim($request->string('start_time'));
        $endDate   = trim($request->string('end_date'));
        $endTime   = trim($request->string('end_time'));

        if (!$startDate || !$startTime || !$endDate || !$endTime) {
            $this->setFlash('error', __('admin.events.error_date_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/events/{$eventId}/edit");
        }

        $startDt = $startDate . ' ' . $startTime . ':00';
        $endDt   = $endDate . ' ' . $endTime . ':00';

        if ($endDt <= $startDt) {
            $this->setFlash('error', __('admin.events.error_end_before_start'));
            return Response::redirect("/admin/tenants/{$tenantId}/events/{$eventId}/edit");
        }

        $maxParticipants = max(1, (int) $request->string('max_participants'));
        $price = $request->string('price') !== '' ? number_format((float) $request->string('price'), 2, '.', '') : null;

        $isRecurring = $request->string('is_recurring') === '1';
        $rrule = null;
        if ($isRecurring) {
            $freq = strtoupper(trim($request->string('frequency')));
            $count = max(1, (int) $request->string('count'));
            if (in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
                $rrule = "FREQ={$freq};COUNT={$count}";
            }
        }

        $exceptionsRaw = trim($request->string('exception_dates'));
        $exceptionDates = null;
        if ($exceptionsRaw !== '') {
            $dates = array_filter(array_map('trim', preg_split('/[\r\n]+/', $exceptionsRaw)));
            $valid = array_filter($dates, fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d));
            if (!empty($valid)) {
                $exceptionDates = json_encode(array_values($valid));
            }
        }

        $allowWaitlist = $request->string('allow_waitlist') === '1' ? 1 : 0;
        $waitlistMax = max(0, (int) $request->string('waitlist_max'));

        Database::execute(
            'UPDATE `events` SET `name` = ?, `description` = ?, `location` = ?, `price` = ?,
             `max_participants` = ?, `start_datetime` = ?, `end_datetime` = ?,
             `is_recurring` = ?, `rrule` = ?, `exception_dates` = ?,
             `allow_waitlist` = ?, `waitlist_max` = ?
             WHERE `id` = ? AND `tenant_id` = ?',
            [
                $name,
                trim($request->string('description')) ?: null,
                trim($request->string('location')) ?: null,
                $price, $maxParticipants, $startDt, $endDt,
                $isRecurring ? 1 : 0, $rrule, $exceptionDates,
                $allowWaitlist, $waitlistMax,
                $eventId, $tenantId,
            ]
        );

        AuditLog::log('event.updated', 'event', $eventId, ['name' => $name], $tenantId);

        $this->setFlash('success', __('admin.events.flash_updated'));
        return Response::redirect("/admin/tenants/{$tenantId}/events");
    }

    public function toggleActive(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $eventId = $request->getAttribute('id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $events = Database::query(
            'SELECT `is_active` FROM `events` WHERE `id` = ? AND `tenant_id` = ?',
            [$eventId, $tenantId]
        );

        if (empty($events)) {
            return Response::redirect("/admin/tenants/{$tenantId}/events");
        }

        $newState = (int) $events[0]['is_active'] === 1 ? 0 : 1;

        Database::execute(
            'UPDATE `events` SET `is_active` = ? WHERE `id` = ? AND `tenant_id` = ?',
            [$newState, $eventId, $tenantId]
        );

        AuditLog::log(
            $newState === 1 ? 'event.activated' : 'event.deactivated',
            'event', $eventId, [], $tenantId
        );

        $msg = $newState === 1 ? __('admin.events.flash_activated') : __('admin.events.flash_deactivated');
        $this->setFlash('success', $msg);
        return Response::redirect("/admin/tenants/{$tenantId}/events");
    }

    public function delete(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $eventId = $request->getAttribute('id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        Database::execute(
            'DELETE FROM `events` WHERE `id` = ? AND `tenant_id` = ?',
            [$eventId, $tenantId]
        );

        AuditLog::log('event.deleted', 'event', $eventId, [], $tenantId);

        $this->setFlash('success', __('admin.events.flash_deleted'));
        return Response::redirect("/admin/tenants/{$tenantId}/events");
    }

    // ── Private helpers ──

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query('SELECT * FROM `tenants` WHERE `id` = ? LIMIT 1', [$tenantId]);
        return $rows[0] ?? null;
    }

    private function render(string $template, string $pageTitle, array $extra, string $tenantId): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'events',
            'csrfToken'  => CsrfMiddleware::generateToken(),
            'tenantId'   => $tenantId,
        ], $extra));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => __('admin.common.forbidden'),
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
}
