<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\BookingService;
use App\Engine\CustomerService;
use App\Engine\Database;
use App\Engine\Logger;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\ResourceCalculator;
use App\Engine\CapacityCalculator;
use App\Engine\TimeSlotCalculator;
use App\Engine\Ulid;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Models\Booking;

/**
 * Booking management controller.
 *
 * Operator routes (cross-tenant): /admin/bookings
 * Tenant-context routes (business user + operator): /admin/tenants/{tenant_id}/bookings
 *
 * GET  /admin/bookings              → Cross-tenant list (operator-only)
 * GET  /admin/bookings/{id}         → Detail (operator-only)
 * POST /admin/bookings/{id}/status  → Update status (operator-only)
 * GET  /admin/tenants/{tenant_id}/bookings              → Per-tenant list
 * GET  /admin/tenants/{tenant_id}/bookings/{id}         → Per-tenant detail
 * POST /admin/tenants/{tenant_id}/bookings/{id}/status  → Per-tenant status update
 */
final class BookingsController
{
    private const PER_PAGE = 25; // PRD §1338

    // ── Operator cross-tenant routes ──

    public function index(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $status = $request->string('status') ?: null;
        $from   = $request->string('from') ?: null;
        $to     = $request->string('to') ?: null;
        $search = $request->string('search') ?: null;
        $sort   = $request->string('sort') ?: 'start_datetime';
        $direction = $request->string('direction') ?: 'desc';
        $page   = max(1, (int) ($request->string('page') ?: 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $bookings = Booking::all($status, $from, $to, $search, self::PER_PAGE, $offset, $sort, $direction);
        $total    = Booking::countAll($status, $from, $to, $search);

        return $this->render('admin.bookings.index', __('admin.bookings.title'), [
            'bookings'         => $bookings,
            'total'            => $total,
            'page'             => $page,
            'perPage'          => self::PER_PAGE,
            'showTenantColumn' => true,
            'filters'          => compact('status', 'from', 'to', 'search', 'sort', 'direction'),
            'sort'             => $sort,
            'direction'        => $direction,
            'flash'            => $this->flash(),
            'backUrl'          => '/admin/bookings',
        ]);
    }

    public function show(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $booking = Booking::find($request->getAttribute('id'));
        if ($booking === null) {
            return Response::redirect('/admin/bookings');
        }

        $timeline = AuditLog::forEntity('booking', $booking['id']);

        return $this->render('admin.bookings.show', __('admin.bookings.title'), [
            'documentTitle' => __('admin.bookings.detail_title'),
            'booking'   => $booking,
            'timeline'  => $timeline,
            'flash'     => $this->flash(),
            'backUrl'   => '/admin/bookings',
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        return $this->doUpdateStatus($request, '/admin/bookings');
    }

    // ── Tenant-context routes (business users + operators) ──

    public function tenantIndex(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        // AuthMiddleware:59-66 already enforced tenant_id match for business users

        $status = $request->string('status') ?: null;
        $from   = $request->string('from') ?: null;
        $to     = $request->string('to') ?: null;
        $sort   = $request->string('sort') ?: 'start_datetime';
        $direction = $request->string('direction') ?: 'desc';
        $page   = max(1, (int) ($request->string('page') ?: 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $bookings = Booking::forTenant($tenantId, $status, $from, $to, self::PER_PAGE, $offset, $sort, $direction);
        $total    = Booking::countForTenant($tenantId, $status, $from, $to);

        return $this->render('admin.bookings.index', __('admin.bookings.title'), [
            'bookings'         => $bookings,
            'total'            => $total,
            'page'             => $page,
            'perPage'          => self::PER_PAGE,
            'showTenantColumn' => false,
            'tenantId'         => $tenantId,
            'filters'          => compact('status', 'from', 'to', 'sort', 'direction'),
            'sort'             => $sort,
            'direction'        => $direction,
            'flash'            => $this->flash(),
            'backUrl'          => "/admin/tenants/{$tenantId}/bookings",
        ]);
    }

    public function tenantShow(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $booking = Booking::find($request->getAttribute('id'));

        if ($booking === null || $booking['tenant_id'] !== $tenantId) {
            return Response::redirect("/admin/tenants/{$tenantId}/bookings");
        }

        $timeline = AuditLog::forEntity('booking', $booking['id']);

        return $this->render('admin.bookings.show', __('admin.bookings.title'), [
            'documentTitle' => __('admin.bookings.detail_title'),
            'booking'   => $booking,
            'timeline'  => $timeline,
            'flash'     => $this->flash(),
            'backUrl'   => "/admin/tenants/{$tenantId}/bookings",
        ]);
    }

    public function tenantUpdateStatus(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $id = $request->getAttribute('id');

        // Guard: verify the booking belongs to this tenant before allowing mutation
        $booking = Booking::find($id);
        if ($booking === null || $booking['tenant_id'] !== $tenantId) {
            $this->setFlash('error', __('admin.bookings.flash_status_failed'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings");
        }

        return $this->doUpdateStatus($request, "/admin/tenants/{$tenantId}/bookings");
    }

    // ── Tenant-context: Manual booking creation ──

    public function tenantCreate(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $isResourcePattern  = ($tenant['booking_pattern'] ?? '') === 'resource';
        $isCapacityPattern  = ($tenant['booking_pattern'] ?? '') === 'capacity';

        // Resource pattern: load resources instead of services/staff
        if ($isResourcePattern) {
            $resources = Database::query(
                'SELECT `id`, `name`, `capacity`, `price_per_night`
                 FROM `resources`
                 WHERE `tenant_id` = ? AND `is_active` = 1
                 ORDER BY `sort_order` ASC, `name` ASC',
                [$tenantId]
            );

            $old = $_SESSION['_old_input'] ?? [];

            return $this->render('admin.tenants.bookings.create-resource', __('admin.bookings.create_title'), [
                'documentTitle' => __('admin.bookings.create_title'),
                'tenant'        => $tenant,
                'tenantId'      => $tenantId,
                'resources'     => $resources,
                'flash'         => $this->flash(),
                'old'           => $old,
            ]);
        }

        // Capacity pattern: load capacity slots
        if ($isCapacityPattern) {
            $slots = Database::query(
                'SELECT `id`, `day_of_week`, `start_time`, `end_time`, `max_capacity`, `max_party_size`, `label`
                 FROM `capacity_slots`
                 WHERE `tenant_id` = ? AND `is_active` = 1
                 ORDER BY `day_of_week` ASC, `start_time` ASC',
                [$tenantId]
            );

            $old = $_SESSION['_old_input'] ?? [];

            return $this->render('admin.tenants.bookings.create-capacity', __('admin.bookings.create_title'), [
                'documentTitle' => __('admin.bookings.create_title'),
                'tenant'        => $tenant,
                'tenantId'      => $tenantId,
                'slots'         => $slots,
                'flash'         => $this->flash(),
                'old'           => $old,
            ]);
        }

        // Timeslot pattern: services + staff
        $services = Database::query(
            'SELECT `id`, `name`, `duration_minutes`, `price`, `price_label`
             FROM `services`
             WHERE `tenant_id` = ? AND `is_active` = 1
             ORDER BY `sort_order` ASC, `name` ASC',
            [$tenantId]
        );

        $staff = Database::query(
            'SELECT `id`, `name`, `title`
             FROM `staff`
             WHERE `tenant_id` = ? AND `is_active` = 1
             ORDER BY `sort_order` ASC',
            [$tenantId]
        );

        // Build service→staff mapping from pivot table for UI filtering.
        // Pre-seed every service with an empty array so services with no
        // pivot rows show "no eligible staff," not "all staff."
        $serviceStaffMap = [];
        foreach ($services as $svc) {
            $serviceStaffMap[$svc['id']] = [];
        }
        $pivotRows = Database::query(
            'SELECT ss.`service_id`, ss.`staff_id`
             FROM `service_staff` ss
             JOIN `staff` s ON s.`id` = ss.`staff_id`
             WHERE s.`tenant_id` = ? AND s.`is_active` = 1',
            [$tenantId]
        );
        foreach ($pivotRows as $row) {
            $serviceStaffMap[$row['service_id']][] = $row['staff_id'];
        }

        // Merge query params (calendar deep link) with old input for prefill
        $old = $_SESSION['_old_input'] ?? [];
        $queryDate = $request->string('date');
        if ($queryDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $queryDate) && !isset($old['date'])) {
            $old['date'] = $queryDate;
        }
        $queryTime = $request->string('time');
        if ($queryTime && preg_match('/^\d{2}:\d{2}$/', $queryTime) && !isset($old['time'])) {
            $old['time'] = $queryTime;
        }

        return $this->render('admin.tenants.bookings.create', __('admin.bookings.create_title'), [
            'documentTitle'   => __('admin.bookings.create_title'),
            'tenant'          => $tenant,
            'tenantId'        => $tenantId,
            'services'        => $services,
            'staff'           => $staff,
            'serviceStaffMap' => $serviceStaffMap,
            'flash'           => $this->flash(),
            'old'             => $old,
        ]);
    }

    public function tenantStore(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        // Dispatch to pattern-specific store
        $pattern = $tenant['booking_pattern'] ?? '';
        if ($pattern === 'resource') {
            return $this->storeResourceBooking($request, $tenant, $tenantId);
        }
        if ($pattern === 'capacity') {
            return $this->storeCapacityBooking($request, $tenant, $tenantId);
        }

        // Collect input
        $serviceId     = $request->string('service_id') ?: null;
        $staffId       = $request->string('staff_id') ?: null;
        $dateStr       = trim($request->string('date'));
        $timeStr       = trim($request->string('time'));
        $customerName  = trim($request->string('customer_name'));
        $customerEmail = trim($request->string('customer_email'));
        $customerPhone = trim($request->string('customer_phone'));
        $notes         = trim($request->string('notes'));

        // Store old input for re-display on validation failure
        $this->storeOldInput($request);

        // ── Validation ──
        if (!$serviceId) {
            $this->setFlash('error', __('admin.bookings.error_service_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($dateStr === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $this->setFlash('error', __('admin.bookings.error_date_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($timeStr === '' || !preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
            $this->setFlash('error', __('admin.bookings.error_time_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($customerName === '') {
            $this->setFlash('error', __('admin.bookings.error_name_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($customerEmail === '') {
            $this->setFlash('error', __('admin.bookings.error_email_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->setFlash('error', __('admin.bookings.error_email_invalid'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ((int) ($tenant['require_phone'] ?? 0) === 1 && $customerPhone === '') {
            $this->setFlash('error', __('admin.bookings.error_phone_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        // ── Validate service/staff pairing ──
        if ($staffId && $serviceId) {
            $pivot = Database::query(
                'SELECT 1 FROM `service_staff` ss
                 JOIN `staff` s ON s.`id` = ss.`staff_id`
                 WHERE ss.`service_id` = ? AND ss.`staff_id` = ? AND s.`tenant_id` = ? AND s.`is_active` = 1
                 LIMIT 1',
                [$serviceId, $staffId, $tenantId]
            );

            if (empty($pivot)) {
                $this->setFlash('error', __('admin.bookings.error_staff_invalid'));
                return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
            }
        }

        // ── Resolve service duration ──
        $serviceDuration = (int) ($tenant['slot_duration_minutes'] ?? 30);
        $service = Database::query(
            'SELECT `duration_minutes` FROM `services` WHERE `id` = ? AND `tenant_id` = ? AND `is_active` = 1 LIMIT 1',
            [$serviceId, $tenantId]
        );
        if (!empty($service)) {
            $serviceDuration = (int) $service[0]['duration_minutes'];
        }

        // ── Calculate start/end ──
        $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
        $startDt = new \DateTimeImmutable("{$dateStr} {$timeStr}", $tz);
        $endDt   = $startDt->modify("+{$serviceDuration} minutes");

        // ── Double-booking prevention: transaction + FOR UPDATE ──
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock existing bookings
            $lockSql = 'SELECT `id` FROM `bookings`
                        WHERE `tenant_id` = ? AND `status` IN (\'confirmed\', \'rescheduled\')
                        AND DATE(`start_datetime`) = ?';
            $lockParams = [$tenantId, $dateStr];

            if ($staffId) {
                $lockSql .= ' AND `staff_id` = ?';
                $lockParams[] = $staffId;
            }

            $lockSql .= ' FOR UPDATE';
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute($lockParams);

            // Re-check availability
            $availResult = TimeSlotCalculator::getAvailableSlots($tenant, $dateStr, $serviceId, $staffId);
            $stillAvailable = false;
            $resolvedStaffId = $staffId;

            foreach ($availResult['slots'] as $slot) {
                if ($slot['time'] === $timeStr) {
                    $stillAvailable = true;
                    if (!$staffId && $slot['staff_id']) {
                        $resolvedStaffId = $slot['staff_id'];
                    }
                    break;
                }
            }

            if (!$stillAvailable) {
                $pdo->rollBack();
                $this->setFlash('error', __('admin.bookings.flash_slot_taken'));
                return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
            }

            // Find or create customer
            $customerId = CustomerService::findOrCreate(
                $tenantId,
                $customerName,
                $customerEmail,
                $customerPhone,
            );

            // Create booking — source='admin', no consent
            $bookingData = [
                'tenant_id'       => $tenantId,
                'customer_id'     => $customerId,
                'booking_pattern' => 'timeslot',
                'start_datetime'  => $startDt->format('Y-m-d H:i:s'),
                'end_datetime'    => $endDt->format('Y-m-d H:i:s'),
                'source'          => 'admin',
            ];

            $bookingData['service_id'] = $serviceId;

            if ($resolvedStaffId) {
                $bookingData['staff_id'] = $resolvedStaffId;
            }

            if ($notes !== '') {
                $bookingData['notes'] = $notes;
            }

            $result = BookingService::createBooking($bookingData, $tenant, false);

            $pdo->commit();

            // After commit: update customer stats
            Database::execute(
                'UPDATE `customers` SET `booking_count` = `booking_count` + 1, `last_booking_at` = NOW() WHERE `id` = ?',
                [$customerId]
            );

            // Clear old input on success
            unset($_SESSION['_old_input']);

            $this->setFlash('success', __('admin.bookings.flash_created'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/{$result['id']}");

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('Admin booking creation failed', [
                'tenant' => $tenantId,
                'error'  => $e->getMessage(),
            ]);

            $this->setFlash('error', __('admin.bookings.flash_create_failed'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }
    }

    // ── Resource-pattern manual booking ──

    private function storeResourceBooking(Request $request, array $tenant, string $tenantId): Response
    {
        $resourceId    = $request->string('resource_id') ?: null;
        $checkIn       = trim($request->string('check_in'));
        $checkOut      = trim($request->string('check_out'));
        $guestCount    = max(1, (int) $request->string('guest_count'));
        $customerName  = trim($request->string('customer_name'));
        $customerEmail = trim($request->string('customer_email'));
        $customerPhone = trim($request->string('customer_phone'));
        $notes         = trim($request->string('notes'));

        $this->storeOldInput($request);

        // Validation
        if (!$resourceId) {
            $this->setFlash('error', __('admin.bookings.error_resource_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($checkIn === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn)) {
            $this->setFlash('error', __('admin.bookings.error_check_in_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($checkOut === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
            $this->setFlash('error', __('admin.bookings.error_check_out_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($customerName === '') {
            $this->setFlash('error', __('admin.bookings.error_name_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->setFlash('error', __('admin.bookings.error_email_invalid'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ((int) ($tenant['require_phone'] ?? 0) === 1 && $customerPhone === '') {
            $this->setFlash('error', __('admin.bookings.error_phone_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        // Check availability (early, pre-lock check for fast feedback)
        $availability = ResourceCalculator::checkAvailability(
            $tenant,
            $resourceId,
            $checkIn,
            $checkOut,
            $guestCount,
        );

        if (!$availability['available']) {
            $this->setFlash('error', __('admin.bookings.flash_resource_unavailable'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        // Double-booking prevention: transaction + FOR UPDATE lock
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock existing bookings for this resource in the date range
            $lockStmt = $pdo->prepare(
                'SELECT `id` FROM `bookings`
                 WHERE `tenant_id` = ? AND `resource_id` = ?
                 AND `status` IN (\'confirmed\', \'rescheduled\')
                 AND `start_datetime` < ? AND `end_datetime` > ?
                 FOR UPDATE'
            );
            $lockStmt->execute([$tenantId, $resourceId, $checkOut . ' 00:00:00', $checkIn . ' 00:00:00']);

            // Re-check availability inside the lock
            $recheck = ResourceCalculator::checkAvailability($tenant, $resourceId, $checkIn, $checkOut, $guestCount);
            if (!$recheck['available']) {
                $pdo->rollBack();
                $this->setFlash('error', __('admin.bookings.flash_resource_unavailable'));
                return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
            }

            $customerId = CustomerService::findOrCreate(
                $tenantId,
                $customerName,
                $customerEmail,
                $customerPhone,
            );

            $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
            $startDt = new \DateTimeImmutable("{$checkIn} 00:00:00", $tz);
            $endDt   = new \DateTimeImmutable("{$checkOut} 00:00:00", $tz);

            $bookingData = [
                'tenant_id'       => $tenantId,
                'customer_id'     => $customerId,
                'booking_pattern' => 'resource',
                'resource_id'     => $resourceId,
                'start_datetime'  => $startDt->format('Y-m-d H:i:s'),
                'end_datetime'    => $endDt->format('Y-m-d H:i:s'),
                'party_size'      => $guestCount,
                'source'          => 'admin',
            ];

            if ($notes !== '') {
                $bookingData['notes'] = $notes;
            }

            $result = BookingService::createBooking($bookingData, $tenant, false);

            $pdo->commit();

            Database::execute(
                'UPDATE `customers` SET `booking_count` = `booking_count` + 1, `last_booking_at` = NOW() WHERE `id` = ?',
                [$customerId]
            );

            unset($_SESSION['_old_input']);
            $this->setFlash('success', __('admin.bookings.flash_created'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/{$result['id']}");

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('Admin resource booking creation failed', [
                'tenant' => $tenantId,
                'error'  => $e->getMessage(),
            ]);

            $this->setFlash('error', __('admin.bookings.flash_create_failed'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }
    }

    // ── Capacity-pattern manual booking ──

    private function storeCapacityBooking(Request $request, array $tenant, string $tenantId): Response
    {
        $slotId        = $request->string('slot_id') ?: null;
        $dateStr       = trim($request->string('date'));
        $partySize     = max(1, (int) $request->string('party_size'));
        $customerName  = trim($request->string('customer_name'));
        $customerEmail = trim($request->string('customer_email'));
        $customerPhone = trim($request->string('customer_phone'));
        $notes         = trim($request->string('notes'));

        $this->storeOldInput($request);

        // Validation
        if (!$slotId) {
            $this->setFlash('error', __('admin.bookings.error_slot_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($dateStr === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $this->setFlash('error', __('admin.bookings.error_date_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($customerName === '') {
            $this->setFlash('error', __('admin.bookings.error_name_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->setFlash('error', __('admin.bookings.error_email_invalid'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        if ((int) ($tenant['require_phone'] ?? 0) === 1 && $customerPhone === '') {
            $this->setFlash('error', __('admin.bookings.error_phone_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        // Pre-lock availability check
        $availability = CapacityCalculator::checkSlotAvailability($tenant, $slotId, $dateStr, $partySize);
        if (!$availability['available']) {
            $this->setFlash('error', __('admin.bookings.flash_capacity_exceeded'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }

        // Load slot for start/end time
        $slotRows = Database::query(
            'SELECT `start_time`, `end_time` FROM `capacity_slots` WHERE `id` = ? AND `tenant_id` = ?',
            [$slotId, $tenantId]
        );
        if (empty($slotRows)) {
            $this->setFlash('error', __('admin.bookings.error_slot_required'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }
        $slot = $slotRows[0];

        // Double-booking prevention: transaction + FOR UPDATE lock
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $startDt = $dateStr . ' ' . $slot['start_time'];
            $endDt   = $dateStr . ' ' . $slot['end_time'];

            // Lock existing capacity bookings for this slot time on this date
            $lockStmt = $pdo->prepare(
                'SELECT `id` FROM `bookings`
                 WHERE `tenant_id` = ? AND `booking_pattern` = \'capacity\'
                 AND `start_datetime` = ? AND `status` IN (\'confirmed\', \'rescheduled\')
                 FOR UPDATE'
            );
            $lockStmt->execute([$tenantId, $startDt]);

            // Re-check availability inside the lock
            $recheck = CapacityCalculator::checkSlotAvailability($tenant, $slotId, $dateStr, $partySize);
            if (!$recheck['available']) {
                $pdo->rollBack();
                $this->setFlash('error', __('admin.bookings.flash_capacity_exceeded'));
                return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
            }

            $customerId = CustomerService::findOrCreate(
                $tenantId,
                $customerName,
                $customerEmail,
                $customerPhone,
            );

            $bookingData = [
                'tenant_id'       => $tenantId,
                'customer_id'     => $customerId,
                'booking_pattern' => 'capacity',
                'start_datetime'  => $startDt,
                'end_datetime'    => $endDt,
                'party_size'      => $partySize,
                'source'          => 'admin',
            ];

            if ($notes !== '') {
                $bookingData['notes'] = $notes;
            }

            $result = BookingService::createBooking($bookingData, $tenant, false);

            $pdo->commit();

            Database::execute(
                'UPDATE `customers` SET `booking_count` = `booking_count` + 1, `last_booking_at` = NOW() WHERE `id` = ?',
                [$customerId]
            );

            unset($_SESSION['_old_input']);
            $this->setFlash('success', __('admin.bookings.flash_created'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/{$result['id']}");

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('Admin capacity booking creation failed', [
                'tenant' => $tenantId,
                'error'  => $e->getMessage(),
            ]);

            $this->setFlash('error', __('admin.bookings.flash_create_failed'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings/create");
        }
    }

    // ── Shared helpers ──

    private function doUpdateStatus(Request $request, string $redirectBase): Response
    {
        $id = $request->getAttribute('id');
        $newStatus = trim($request->string('status'));
        $booking = Booking::find($id);

        if ($booking === null) {
            $this->setFlash('error', __('admin.bookings.flash_status_failed'));
            return Response::redirect($redirectBase);
        }

        $oldStatus = $booking['status'];

        if (Booking::updateStatus($id, $newStatus)) {
            AuditLog::log('booking.status_changed', 'booking', $id, [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
            $this->setFlash('success', __('admin.bookings.flash_status_updated'));
        } else {
            $this->setFlash('error', __('admin.bookings.flash_status_failed'));
        }

        return Response::redirect("{$redirectBase}/{$id}");
    }

    private function render(string $template, string $pageTitle, array $extra = []): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'bookings',
            'csrfToken'  => CsrfMiddleware::generateToken(),
        ], $extra));
    }

    /**
     * Load full tenant record. Returns null if not found.
     */
    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );

        return $rows[0] ?? null;
    }

    /**
     * Store form input in session for re-display on validation failure.
     *
     * Stores all submitted fields (both timeslot and resource patterns)
     * so the form can be repopulated after a redirect.
     */
    private function storeOldInput(Request $request): void
    {
        Auth::startSession();
        $old = $_POST;
        unset($old['_csrf_token']);
        $_SESSION['_old_input'] = $old;
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Operator access required.',
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
