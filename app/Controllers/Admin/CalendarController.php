<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\Locale;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Models\Booking;

/**
 * Calendar controller (tenant-scoped).
 *
 * Access: all authenticated admin roles (operator, owner, manager).
 *
 * GET /admin/tenants/{tenant_id}/calendar          → day view (default: today)
 * GET /admin/tenants/{tenant_id}/calendar/week     → week view (default: this week)
 */
final class CalendarController
{
    private const HOUR_START = 7;   // Calendar starts at 07:00
    private const HOUR_END   = 21;  // Calendar ends at 21:00

    // ── Day View ──

    public function day(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $dateStr = $request->string('date') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $dateStr = date('Y-m-d');
        }

        $date     = new \DateTimeImmutable($dateStr);
        $prevDate = $date->modify('-1 day')->format('Y-m-d');
        $nextDate = $date->modify('+1 day')->format('Y-m-d');
        $today    = date('Y-m-d');
        $isToday  = $dateStr === $today;

        $bookings = Booking::forTenantDate($tenantId, $dateStr);

        // Build hour slots for the timeline
        $hours = [];
        for ($h = self::HOUR_START; $h <= self::HOUR_END; $h++) {
            $hours[] = $h;
        }

        return $this->render('admin.tenants.calendar.day', __('admin.calendar.page_title'), [
            'documentTitle' => __('admin.calendar.page_title') . ' — ' . $tenant['name'],
            'tenant'    => $tenant,
            'date'      => $date,
            'dateStr'   => $dateStr,
            'prevDate'  => $prevDate,
            'nextDate'  => $nextDate,
            'isToday'   => $isToday,
            'today'     => $today,
            'bookings'  => $bookings,
            'hours'     => $hours,
            'hourStart' => self::HOUR_START,
            'hourEnd'   => self::HOUR_END,
        ], $tenantId);
    }

    // ── Week View ──

    public function week(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $dateStr = $request->string('date') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $dateStr = date('Y-m-d');
        }

        $date      = new \DateTimeImmutable($dateStr);
        $weekStart = Locale::weekStart(); // 0=Sunday, 1=Monday
        $today     = date('Y-m-d');

        // Calculate week boundaries
        $currentDow = (int) $date->format('w'); // 0=Sun..6=Sat
        $daysBack   = ($currentDow - $weekStart + 7) % 7;
        $weekBegin  = $date->modify("-{$daysBack} days");

        $prevWeek = $weekBegin->modify('-7 days')->format('Y-m-d');
        $nextWeek = $weekBegin->modify('+7 days')->format('Y-m-d');

        // Build 7 days with their bookings
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $dayDate = $weekBegin->modify("+{$i} days");
            $dayStr  = $dayDate->format('Y-m-d');
            $days[]  = [
                'date'      => $dayDate,
                'dateStr'   => $dayStr,
                'isToday'   => $dayStr === $today,
                'bookings'  => Booking::forTenantDate($tenantId, $dayStr),
            ];
        }

        return $this->render('admin.tenants.calendar.week', __('admin.calendar.page_title'), [
            'documentTitle' => __('admin.calendar.page_title') . ' — ' . $tenant['name'],
            'tenant'    => $tenant,
            'date'      => $date,
            'dateStr'   => $dateStr,
            'weekBegin' => $weekBegin,
            'prevWeek'  => $prevWeek,
            'nextWeek'  => $nextWeek,
            'today'     => $today,
            'days'      => $days,
        ], $tenantId);
    }

    // ── Helpers ──

    private function loadTenant(string $tenantId): ?array
    {
        $rows = \App\Engine\Database::query(
            'SELECT `id`, `name`, `slug`, `email`, `timezone`, `currency`, `brand_color` FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );

        return $rows[0] ?? null;
    }

    private function render(string $template, string $pageTitle, array $extra, string $tenantId): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'calendar',
            'csrfToken'  => CsrfMiddleware::generateToken(),
            'tenantId'   => $tenantId,
        ], $extra));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Access denied.',
            ], 403);
        }

        return View::response('admin.errors.403', [
            'user'      => Auth::user(),
            'version'   => Version::get(),
            'pageTitle' => '403',
            'csrfToken' => CsrfMiddleware::generateToken(),
        ], 403);
    }
}
