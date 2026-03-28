<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
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
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE `start_datetime` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
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
