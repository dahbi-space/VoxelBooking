<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\Database;
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
 * GET /admin/tenants/{tenant_id}/calendar          → month view (default, flagship surface)
 * GET /admin/tenants/{tenant_id}/calendar/day      → day view (detail drill-down)
 * GET /admin/tenants/{tenant_id}/calendar/week     → week view
 *
 * All views load:
 * - Bookings (from `bookings` table)
 * - Blocked dates (from `blocked_dates` table, tenant-level)
 * - Availability schedule (from `availability` table, day-of-week patterns)
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

        // Assign columns for concurrent bookings (side-by-side layout)
        $bookings = $this->assignOverlapColumns($bookings);

        // Build hour slots for the timeline
        $hours = [];
        for ($h = self::HOUR_START; $h <= self::HOUR_END; $h++) {
            $hours[] = $h;
        }

        // Availability / blocked state for this specific day
        $isBlocked = $this->isDateBlocked($tenantId, $dateStr);
        $dayOfWeek = $this->isoDayOfWeek($date);
        $hasAvailability = $this->hasDayOfWeekAvailability($tenantId, $dayOfWeek);

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
            'isBlocked'      => $isBlocked,
            'hasAvailability' => $hasAvailability,
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

        // Pre-load availability dow map and blocked dates for the week range
        $weekEndStr = $weekBegin->modify('+6 days')->format('Y-m-d');
        $availableDows = $this->getAvailableDaysOfWeek($tenantId);
        $blockedDatesInRange = $this->getBlockedDatesInRange($tenantId, $weekBegin->format('Y-m-d'), $weekEndStr);

        // Build 7 days with their bookings + state
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $dayDate = $weekBegin->modify("+{$i} days");
            $dayStr  = $dayDate->format('Y-m-d');
            $dow     = $this->isoDayOfWeek($dayDate);
            $days[]  = [
                'date'            => $dayDate,
                'dateStr'         => $dayStr,
                'isToday'         => $dayStr === $today,
                'bookings'        => Booking::forTenantDate($tenantId, $dayStr),
                'isBlocked'       => in_array($dayStr, $blockedDatesInRange, true),
                'hasAvailability' => in_array($dow, $availableDows, true),
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

    // ── Month View ──

    public function month(Request $request): Response
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
        $today     = date('Y-m-d');
        $year      = (int) $date->format('Y');
        $month     = (int) $date->format('n');
        $weekStart = Locale::weekStart(); // 0=Sunday, 1=Monday

        // Previous / next month navigation
        $prevMonth = $date->modify('first day of previous month')->format('Y-m-d');
        $nextMonth = $date->modify('first day of next month')->format('Y-m-d');

        // First day of the month and total days
        $firstOfMonth = new \DateTimeImmutable("$year-$month-01");
        $daysInMonth  = (int) $firstOfMonth->format('t');

        // Calculate grid padding: how many days from previous month to show
        $firstDow    = (int) $firstOfMonth->format('w'); // 0=Sun..6=Sat
        $paddingBefore = ($firstDow - $weekStart + 7) % 7;

        // Grid start date (may be in previous month)
        $gridStart = $firstOfMonth->modify("-{$paddingBefore} days");

        // Always show 6 rows = 42 cells for a consistent grid height
        $totalCells   = 42;
        $gridEnd      = $gridStart->modify('+' . ($totalCells - 1) . ' days');

        // Fetch all bookings in the visible range with a single query
        $allBookings = Booking::forTenantDateRange(
            $tenantId,
            $gridStart->format('Y-m-d'),
            $gridEnd->format('Y-m-d')
        );

        // Group bookings by date for O(1) lookup in template
        $bookingsByDate = [];
        foreach ($allBookings as $b) {
            $bDate = substr($b['start_datetime'], 0, 10);
            $bookingsByDate[$bDate][] = $b;
        }

        // Pre-load availability + blocked dates for the grid range
        $availableDows = $this->getAvailableDaysOfWeek($tenantId);
        $blockedDatesInRange = $this->getBlockedDatesInRange(
            $tenantId,
            $gridStart->format('Y-m-d'),
            $gridEnd->format('Y-m-d')
        );

        // Build the grid cells
        $cells = [];
        for ($i = 0; $i < $totalCells; $i++) {
            $cellDate = $gridStart->modify("+{$i} days");
            $cellStr  = $cellDate->format('Y-m-d');
            $cellMonth = (int) $cellDate->format('n');
            $dow      = $this->isoDayOfWeek($cellDate);
            $cells[] = [
                'date'            => $cellDate,
                'dateStr'         => $cellStr,
                'day'             => (int) $cellDate->format('j'),
                'isToday'         => $cellStr === $today,
                'isCurrentMonth'  => $cellMonth === $month,
                'bookings'        => $bookingsByDate[$cellStr] ?? [],
                'isBlocked'       => in_array($cellStr, $blockedDatesInRange, true),
                'hasAvailability' => in_array($dow, $availableDows, true),
            ];
        }

        return $this->render('admin.tenants.calendar.month', __('admin.calendar.page_title'), [
            'documentTitle' => __('admin.calendar.page_title') . ' — ' . $tenant['name'],
            'tenant'    => $tenant,
            'date'      => $date,
            'dateStr'   => $dateStr,
            'year'      => $year,
            'month'     => $month,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'today'     => $today,
            'cells'     => $cells,
        ], $tenantId);
    }

    // ── Availability & Blocked Dates Helpers ──

    /**
     * Check if a specific date is blocked for the given tenant (tenant-level blocks only).
     */
    private function isDateBlocked(string $tenantId, string $dateStr): bool
    {
        $rows = Database::query(
            'SELECT 1 FROM `blocked_dates`
             WHERE `tenant_id` = ? AND `staff_id` IS NULL AND `resource_id` IS NULL
               AND `start_date` <= ? AND `end_date` >= ?
             LIMIT 1',
            [$tenantId, $dateStr, $dateStr]
        );

        return !empty($rows);
    }

    /**
     * Check if any availability window exists for a given day of week.
     * Checks both `availability` (timeslot) and `capacity_slots` (capacity) tables.
     */
    private function hasDayOfWeekAvailability(string $tenantId, int $dayOfWeek): bool
    {
        $rows = Database::query(
            'SELECT 1 FROM `availability`
             WHERE `tenant_id` = ? AND `staff_id` IS NULL AND `day_of_week` = ?
             UNION ALL
             SELECT 1 FROM `capacity_slots`
             WHERE `tenant_id` = ? AND `day_of_week` = ? AND `is_active` = 1
             LIMIT 1',
            [$tenantId, $dayOfWeek, $tenantId, $dayOfWeek]
        );

        return !empty($rows);
    }

    /**
     * Get all days-of-week that have at least one availability window.
     * Checks both `availability` (timeslot) and `capacity_slots` (capacity) tables.
     * Returns array of integers (0=Mon..6=Sun, matching ISO convention).
     */
    private function getAvailableDaysOfWeek(string $tenantId): array
    {
        $rows = Database::query(
            'SELECT DISTINCT `day_of_week` FROM (
                SELECT `day_of_week` FROM `availability`
                WHERE `tenant_id` = ? AND `staff_id` IS NULL
                UNION ALL
                SELECT `day_of_week` FROM `capacity_slots`
                WHERE `tenant_id` = ? AND `is_active` = 1
             ) AS combined',
            [$tenantId, $tenantId]
        );

        return array_map(fn($r) => (int) $r['day_of_week'], $rows);
    }

    /**
     * Get all blocked dates (expanded) within a range for the tenant.
     * Returns array of date strings ['2026-04-05', '2026-04-06', ...].
     */
    private function getBlockedDatesInRange(string $tenantId, string $startDate, string $endDate): array
    {
        $rows = Database::query(
            'SELECT `start_date`, `end_date` FROM `blocked_dates`
             WHERE `tenant_id` = ? AND `staff_id` IS NULL AND `resource_id` IS NULL
               AND `start_date` <= ? AND `end_date` >= ?',
            [$tenantId, $endDate, $startDate]
        );

        // Expand ranges into individual date strings
        $blocked = [];
        foreach ($rows as $r) {
            $current = new \DateTimeImmutable($r['start_date']);
            $end     = new \DateTimeImmutable($r['end_date']);
            while ($current <= $end) {
                $ds = $current->format('Y-m-d');
                // Only include dates within the requested range
                if ($ds >= $startDate && $ds <= $endDate) {
                    $blocked[] = $ds;
                }
                $current = $current->modify('+1 day');
            }
        }

        return array_unique($blocked);
    }

    // ── Shared Helpers ──

    /**
     * Assign side-by-side columns for concurrent bookings.
     *
     * Uses a greedy column-packing algorithm: sort by start, assign each
     * booking to the leftmost column where it doesn't overlap with any
     * existing booking. Each booking gets `colIndex` (0-based) and
     * `colTotal` (max columns in its overlap group).
     *
     * @param  array<int, array<string, mixed>>  $bookings
     * @return array<int, array<string, mixed>>
     */
    private function assignOverlapColumns(array $bookings): array
    {
        if (count($bookings) <= 1) {
            foreach ($bookings as &$b) {
                $b['colIndex'] = 0;
                $b['colTotal'] = 1;
            }
            return $bookings;
        }

        // Sort by start time, then by end time (broader first)
        usort($bookings, function (array $a, array $b): int {
            $cmp = $a['start_datetime'] <=> $b['start_datetime'];
            return $cmp !== 0 ? $cmp : ($a['end_datetime'] <=> $b['end_datetime']);
        });

        // Track column end-times: $columns[$col] = latest end_datetime in that column
        $columns = [];

        foreach ($bookings as &$booking) {
            $start = $booking['start_datetime'];
            $placed = false;

            // Try to fit into the leftmost available column
            foreach ($columns as $col => $colEnd) {
                if ($start >= $colEnd) {
                    $columns[$col] = $booking['end_datetime'];
                    $booking['colIndex'] = $col;
                    $placed = true;
                    break;
                }
            }

            if (!$placed) {
                $booking['colIndex'] = count($columns);
                $columns[] = $booking['end_datetime'];
            }
        }
        unset($booking);

        // Now find connected overlap groups and assign colTotal
        $totalCols = count($columns);
        foreach ($bookings as &$booking) {
            $booking['colTotal'] = $totalCols;
        }
        unset($booking);

        return $bookings;
    }

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT `id`, `name`, `slug`, `email`, `timezone`, `currency`, `brand_color`, `week_start`, `time_format` FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );

        $tenant = $rows[0] ?? null;

        if ($tenant !== null) {
            // Apply tenant-level calendar overrides to the Locale engine
            Locale::setTenantOverrides([
                'week_start'   => $tenant['week_start'] !== null ? (int) $tenant['week_start'] : null,
                'time_format'  => $tenant['time_format'] ?: null,
            ]);
        }

        return $tenant;
    }

    /**
     * Convert PHP weekday numbering to the availability table's ISO convention.
     * PHP `N`: 1=Mon .. 7=Sun → DB: 0=Mon .. 6=Sun.
     */
    private function isoDayOfWeek(\DateTimeInterface $date): int
    {
        return (int) $date->format('N') - 1;
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
