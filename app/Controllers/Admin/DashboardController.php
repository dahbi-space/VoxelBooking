<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Models\Booking;
use App\Models\Tenant;

/**
 * Operator dashboard controller.
 *
 * Queries real tenant/booking metrics from the database,
 * including week-over-week deltas and upcoming bookings.
 */
final class DashboardController
{
    public function index(Request $request): Response
    {
        // Operator dashboard — business users should never reach this
        // (AuthMiddleware redirects them to /admin/tenants/{id}, but belt-and-suspenders)
        if (!Auth::isOperator()) {
            return Response::redirect('/admin/login');
        }

        $tenantCounts = Tenant::counts();

        $today = date('Y-m-d');
        $todayEnd = $today . ' 23:59:59';
        $now = date('Y-m-d H:i:s');
        $weekStart = date('Y-m-d', strtotime('-6 days'));
        $now24h = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $prevDay = date('Y-m-d', strtotime('-7 days'));
        $prevWeekStart = date('Y-m-d', strtotime('-13 days'));
        $prevWeekEnd = date('Y-m-d', strtotime('-7 days'));

        $todayBookings = $this->queryCount(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE DATE(`start_datetime`) = ?",
            [$today]
        );
        $weekBookings = $this->queryCount(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE `start_datetime` >= ? AND `start_datetime` <= ?",
            [$weekStart, $todayEnd]
        );
        $upcoming24h = $this->queryCount(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE `start_datetime` BETWEEN ? AND ? AND `status` = 'confirmed'",
            [$now, $now24h]
        );

        // Deltas: same-day-last-week for daily, previous-7-day-window for weekly
        $prevTodayBookings = $this->queryCount(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE DATE(`start_datetime`) = ?",
            [$prevDay]
        );
        $prevWeekBookings = $this->queryCount(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE `start_datetime` >= ? AND `start_datetime` < ?",
            [$prevWeekStart, $prevWeekEnd]
        );

        $deltaToday = $todayBookings - $prevTodayBookings;
        $deltaWeek = $weekBookings - $prevWeekBookings;

        // Cross-tenant upcoming bookings list
        $upcoming = Booking::allUpcoming(date('Y-m-d H:i:s'), 10);

        return View::response('admin.dashboard', [
            'user'           => Auth::user(),
            'version'        => Version::get(),
            'activeTenants'  => $tenantCounts['active'],
            'tenantCounts'   => $tenantCounts,
            'todayBookings'  => $todayBookings,
            'weekBookings'   => $weekBookings,
            'upcoming24h'    => $upcoming24h,
            'deltaToday'     => $deltaToday,
            'deltaWeek'      => $deltaWeek,
            'upcoming'       => $upcoming,
        ]);
    }

    /**
     * Tenant-scoped business dashboard.
     *
     * Business users land here via AuthMiddleware redirect from /admin.
     * Operators can also view any tenant's dashboard.
     */
    public function tenantDashboard(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        // Business user: verify tenant ownership
        if (Auth::isBusinessUser()) {
            $user = Auth::user();
            if (($user['tenant_id'] ?? '') !== $tenantId) {
                return Response::redirect('/admin');
            }
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin');
        }

        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('-6 days'));
        $now = date('Y-m-d H:i:s');

        $todayBookings = Booking::countForTenant($tenantId, null, $today, $today);
        $weekBookings = Booking::countForTenant($tenantId, null, $weekStart, $today);

        // Deltas: same-day-last-week for daily, previous-7-day-window for weekly
        $prevDay = date('Y-m-d', strtotime('-7 days'));
        $prevWeekStart = date('Y-m-d', strtotime('-13 days'));
        $prevWeekEnd = date('Y-m-d', strtotime('-7 days'));

        $prevTodayBookings = Booking::countForTenant($tenantId, null, $prevDay, $prevDay);
        $prevWeekBookings = Booking::countForTenant($tenantId, null, $prevWeekStart, $prevWeekEnd);

        $deltaToday = $todayBookings - $prevTodayBookings;
        $deltaWeek = $weekBookings - $prevWeekBookings;

        // Today's schedule for the schedule strip
        $todaySchedule = Booking::forTenantDate($tenantId, $today);

        return View::response('admin.dashboard-business', [
            'user'           => Auth::user(),
            'version'        => Version::get(),
            'pageTitle'      => $tenant['name'],
            'activePage'     => 'dashboard',
            'csrfToken'      => \App\Middleware\CsrfMiddleware::generateToken(),
            'tenant'         => $tenant,
            'todayBookings'  => $todayBookings,
            'weekBookings'   => $weekBookings,
            'statusCounts'   => Booking::statusCounts($tenantId),
            'upcoming'       => Booking::forTenantUpcoming($tenantId, $now, 5),
            'deltaToday'     => $deltaToday,
            'deltaWeek'      => $deltaWeek,
            'todaySchedule'  => $todaySchedule,
        ]);
    }

    private function queryCount(string $sql, array $bindings = []): int
    {
        try {
            $rows = Database::query($sql, $bindings);
            return (int) ($rows[0]['cnt'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
