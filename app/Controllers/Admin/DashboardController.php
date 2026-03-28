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
 * Queries real tenant/booking metrics from the database.
 */
final class DashboardController
{
    public function index(Request $request): Response
    {
        $tenantCounts = Tenant::counts();

        $todayBookings = $this->queryCount(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE DATE(`start_datetime`) = CURDATE()"
        );
        $weekBookings = $this->queryCount(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE `start_datetime` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND `start_datetime` <= CONCAT(CURDATE(), ' 23:59:59')"
        );
        $upcoming24h = $this->queryCount(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE `start_datetime` BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR) AND `status` = 'confirmed'"
        );

        return View::response('admin.dashboard', [
            'user'           => Auth::user(),
            'version'        => Version::get(),
            'activeTenants'  => $tenantCounts['active'],
            'tenantCounts'   => $tenantCounts,
            'todayBookings'  => $todayBookings,
            'weekBookings'   => $weekBookings,
            'upcoming24h'    => $upcoming24h,
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
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $now = date('Y-m-d H:i:s');

        return View::response('admin.dashboard-business', [
            'user'          => Auth::user(),
            'version'       => Version::get(),
            'pageTitle'     => $tenant['name'],
            'activePage'    => 'dashboard',
            'csrfToken'     => \App\Middleware\CsrfMiddleware::generateToken(),
            'tenant'        => $tenant,
            'todayBookings' => Booking::countForTenant($tenantId, null, $today, $today),
            'weekBookings'  => Booking::countForTenant($tenantId, null, $weekAgo, $today),
            'statusCounts'  => Booking::statusCounts($tenantId),
            'upcoming'      => Booking::forTenantUpcoming($tenantId, $now, 5),
        ]);
    }

    private function queryCount(string $sql): int
    {
        try {
            $rows = Database::query($sql);
            return (int) ($rows[0]['cnt'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
