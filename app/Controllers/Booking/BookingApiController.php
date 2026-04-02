<?php

declare(strict_types=1);

namespace App\Controllers\Booking;

use App\Engine\CustomerService;
use App\Engine\Database;
use App\Engine\Locale;
use App\Engine\TimeSlotCalculator;
use App\Engine\ResourceCalculator;
use App\Engine\CapacityCalculator;
use App\Engine\EventCalculator;
use App\Engine\BookingService;
use App\Engine\Ulid;
use App\Engine\AuditLog;
use App\Engine\Logger;
use App\Engine\Mailer;
use App\Engine\Request;
use App\Engine\Response;

/**
 * Public API controller for booking pages.
 *
 * All endpoints are per-tenant, resolved via {slug} route parameter.
 * No authentication required — these serve the public booking page.
 *
 * Rate limits per PRD §X:
 * - GET endpoints: 60/min/IP
 * - POST /bookings: 10/min/IP
 */
final class BookingApiController
{
    /**
     * Resolve tenant from slug. Returns null if not found or not active.
     */
    private function resolveTenant(string $slug): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `tenants` WHERE `slug` = ? AND `status` = ? LIMIT 1',
            [$slug, 'active']
        );

        return $rows[0] ?? null;
    }

    /**
     * Apply the public booking locale resolution chain.
     *
     * Must be called before any __() response to ensure the locale
     * matches what the booking page shell uses.
     */
    private function resolveLocale(array $tenant, Request $request): void
    {
        $acceptLang = $request->header('Accept-Language');
        Locale::resolveForBooking($tenant, $acceptLang);
    }

    /**
     * GET /api/{slug}/services — list active services.
     */
    public function services(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $services = Database::query(
            'SELECT `id`, `name`, `description`, `duration_minutes`, `price`, `price_label`, `category`, `preparation_text`
             FROM `services`
             WHERE `tenant_id` = ? AND `is_active` = 1
             ORDER BY `sort_order` ASC, `name` ASC',
            [$tenant['id']]
        );

        return Response::json(['services' => $services]);
    }

    /**
     * GET /api/{slug}/staff — list active staff.
     */
    public function staff(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $serviceId = $request->string('service_id');

        if ($serviceId) {
            // Staff for specific service
            $staff = Database::query(
                'SELECT s.`id`, s.`name`, s.`title`, s.`avatar_path`
                 FROM `staff` s
                 JOIN `service_staff` ss ON ss.`staff_id` = s.`id`
                 WHERE ss.`service_id` = ? AND s.`tenant_id` = ? AND s.`is_active` = 1
                 ORDER BY s.`sort_order` ASC',
                [$serviceId, $tenant['id']]
            );
        } else {
            $staff = Database::query(
                'SELECT `id`, `name`, `title`, `avatar_path`
                 FROM `staff`
                 WHERE `tenant_id` = ? AND `is_active` = 1
                 ORDER BY `sort_order` ASC',
                [$tenant['id']]
            );
        }

        return Response::json(['staff' => $staff]);
    }

    /**
     * GET /api/{slug}/availability — available slots for a date.
     */
    public function availability(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $this->resolveLocale($tenant, $request);

        $date = $request->string('date');
        $serviceId = $request->string('service_id');
        $staffId = $request->string('staff_id');

        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Response::json(['error' => 'invalid_date', 'message' => __('booking.api.invalid_date')], 400);
        }

        $result = TimeSlotCalculator::getAvailableSlots($tenant, $date, $serviceId ?: null, $staffId ?: null);

        return Response::json($result);
    }

    /**
     * GET /api/{slug}/available-dates — dates with slots in a month.
     */
    public function availableDates(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $year = (int) ($request->string('year') ?: date('Y'));
        $month = (int) ($request->string('month') ?: date('n'));
        $serviceId = $request->string('service_id');
        $staffId = $request->string('staff_id');

        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2040) {
            return Response::json(['error' => 'invalid_month'], 400);
        }

        $dates = TimeSlotCalculator::getAvailableDates($tenant, $year, $month, $serviceId ?: null, $staffId ?: null);

        return Response::json(['dates' => $dates, 'year' => $year, 'month' => $month]);
    }

    /**
     * POST /api/{slug}/bookings — create a booking.
     *
     * Implements double-booking prevention per PRD §III:
     * 1. Begin transaction
     * 2. SELECT ... FOR UPDATE on time window
     * 3. Re-check availability
     * 4. Insert booking + customer
     * 5. Commit
     * 6. After commit: dispatch emails
     */
    // ── Resource-pattern endpoints ──

    /**
     * GET /api/{slug}/resources — list active resources.
     */
    public function resources(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $resources = Database::query(
            'SELECT `id`, `name`, `description`, `capacity`, `cover_image_path`, `amenities`,
                    `price_per_night`, `min_stay_nights`, `max_stay_nights`
             FROM `resources`
             WHERE `tenant_id` = ? AND `is_active` = 1
             ORDER BY `sort_order` ASC, `name` ASC',
            [$tenant['id']]
        );

        // Decode amenities JSON for each resource
        foreach ($resources as &$r) {
            $r['amenities'] = $r['amenities'] ? json_decode($r['amenities'], true) : [];
            $r['price_per_night'] = $r['price_per_night'] !== null ? (float) $r['price_per_night'] : null;
            $r['capacity'] = (int) $r['capacity'];
            $r['min_stay_nights'] = (int) $r['min_stay_nights'];
            $r['max_stay_nights'] = (int) $r['max_stay_nights'];
        }
        unset($r);

        return Response::json(['resources' => $resources]);
    }

    /**
     * GET /api/{slug}/resources/{id}/availability — available dates for a resource.
     */
    public function resourceAvailability(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $resourceId = $request->getAttribute('id');

        // Range-check mode: check_in + check_out → full availability + pricing
        $checkIn = $request->string('check_in');
        $checkOut = $request->string('check_out');

        if ($checkIn && $checkOut) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
                return Response::json(['error' => 'invalid_date_range'], 400);
            }

            $guestCount = max(1, (int) ($request->string('guests') ?: 1));

            $result = ResourceCalculator::checkAvailability(
                $tenant,
                $resourceId,
                $checkIn,
                $checkOut,
                $guestCount,
            );

            return Response::json($result);
        }

        // Month-view mode: year + month → list of available dates
        $year = (int) ($request->string('year') ?: date('Y'));
        $month = (int) ($request->string('month') ?: date('n'));

        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2040) {
            return Response::json(['error' => 'invalid_month'], 400);
        }

        $result = ResourceCalculator::getAvailableDates($tenant, $resourceId, $year, $month);

        return Response::json($result);
    }

    // ── Booking creation ──

    /**
     * POST /api/{slug}/bookings — create a booking.
     *
     * Dispatches to pattern-specific creation logic based on the tenant's booking_pattern.
     * Timeslot: double-booking prevention per PRD §III.
     * Resource: date-range availability check + capacity validation.
     * Capacity: party-size slot availability + overbooking prevention.
     */
    public function createBooking(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $this->resolveLocale($tenant, $request);

        $input = $request->json();

        if (empty($input)) {
            return Response::json(['error' => 'invalid_json'], 400);
        }

        // Anti-spam: check __ts (timestamp field populated by JS on page load)
        $ts = $input['__ts'] ?? null;
        if (!$ts || !is_numeric($ts)) {
            return Response::json(['error' => 'spam_detected', 'message' => __('booking.api.spam_detected')], 422);
        }
        $pageLoadTime = (int) $ts;
        $elapsed = time() * 1000 - $pageLoadTime;
        if ($elapsed < 3000) {
            return Response::json(['error' => 'spam_detected', 'message' => __('booking.api.spam_retry')], 422);
        }

        // Anti-spam: honeypot (hidden field — bots fill it, humans don't)
        if (trim($input['__hp'] ?? '') !== '') {
            return Response::json(['error' => 'spam_detected', 'message' => __('booking.api.spam_detected')], 422);
        }

        // CSRF: manually validate for booking POST (API routes skip middleware CSRF)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        $submittedToken = $request->header('X-CSRF-Token') ?? '';
        if ($sessionToken === '' || $submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
            return Response::json(['error' => 'csrf_mismatch', 'message' => __('booking.api.csrf_mismatch')], 403);
        }

        // Validate required fields
        $customer = $input['customer'] ?? [];
        $customerName = trim($customer['name'] ?? '');
        $customerEmail = trim($customer['email'] ?? '');
        $customerPhone = trim($customer['phone'] ?? '');

        if ($customerName === '' || $customerEmail === '') {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.name_email_required')], 422);
        }

        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.invalid_email')], 422);
        }

        if ((int) $tenant['require_phone'] === 1 && $customerPhone === '') {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.phone_required')], 422);
        }

        // Pattern dispatch: route to pattern-specific creation logic
        $pattern = $tenant['booking_pattern'] ?? 'timeslot';
        if ($pattern === 'resource') {
            return $this->createResourceBooking($tenant, $input, $customerName, $customerEmail, $customerPhone);
        }
        if ($pattern === 'capacity') {
            return $this->createCapacityBooking($tenant, $input, $customerName, $customerEmail, $customerPhone);
        }
        if ($pattern === 'event') {
            return $this->createEventBooking($tenant, $input, $customerName, $customerEmail, $customerPhone);
        }

        // ── Timeslot-specific validation and booking creation ──

        $serviceId = $input['service_id'] ?? null;
        $staffId = $input['staff_id'] ?? null;
        $startDatetime = $input['start_datetime'] ?? null;
        $consentGiven = (bool) ($input['consent_given'] ?? false);

        if (!$startDatetime) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.start_time_required')], 422);
        }

        // Resolve service for duration
        $serviceDuration = (int) ($tenant['slot_duration_minutes'] ?? 30);
        if ($serviceId) {
            $service = Database::query(
                'SELECT `duration_minutes` FROM `services` WHERE `id` = ? AND `tenant_id` = ? AND `is_active` = 1 LIMIT 1',
                [$serviceId, $tenant['id']]
            );
            if (!empty($service)) {
                $serviceDuration = (int) $service[0]['duration_minutes'];
            }
        }

        // Calculate end time
        $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
        $startDt = new \DateTimeImmutable($startDatetime, $tz);
        $endDt = $startDt->modify("+{$serviceDuration} minutes");
        $date = $startDt->format('Y-m-d');
        $startTime = $startDt->format('H:i');

        // Double-booking prevention: transaction + FOR UPDATE lock
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock existing bookings in the time window
            $lockSql = 'SELECT `id` FROM `bookings`
                        WHERE `tenant_id` = ? AND `status` IN (\'confirmed\', \'rescheduled\')
                        AND DATE(`start_datetime`) = ?';
            $lockParams = [$tenant['id'], $date];

            if ($staffId) {
                $lockSql .= ' AND `staff_id` = ?';
                $lockParams[] = $staffId;
            }

            $lockSql .= ' FOR UPDATE';
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute($lockParams);

            // Re-check availability inside the lock
            $availResult = TimeSlotCalculator::getAvailableSlots($tenant, $date, $serviceId, $staffId);
            $stillAvailable = false;
            $resolvedStaffId = $staffId;

            foreach ($availResult['slots'] as $slot) {
                if ($slot['time'] === $startTime) {
                    $stillAvailable = true;
                    if (!$staffId && $slot['staff_id']) {
                        $resolvedStaffId = $slot['staff_id'];
                    }
                    break;
                }
            }

            if (!$stillAvailable) {
                $pdo->rollBack();

                $alternatives = array_slice(
                    array_map(fn($s) => ['time' => $s['time'], 'end_time' => $s['end_time'], 'staff_id' => $s['staff_id']], $availResult['slots']),
                    0,
                    3
                );

                return Response::json([
                    'error'        => 'slot_unavailable',
                    'message'      => __('booking.api.slot_unavailable'),
                    'alternatives' => $alternatives,
                ], 409);
            }

            // Find or create customer
            $customerId = CustomerService::findOrCreate(
                $tenant['id'],
                $customerName,
                $customerEmail,
                $customerPhone,
            );

            // Transaction-safe daily limit: lock customer row, then count
            $maxPerDay = (int) ($tenant['max_bookings_per_customer_per_day'] ?? 3);
            if ($maxPerDay > 0) {
                // Serialize concurrent requests for this customer within the transaction
                Database::query(
                    'SELECT `id` FROM `customers` WHERE `id` = ? FOR UPDATE',
                    [$customerId]
                );

                $countRows = Database::query(
                    'SELECT COUNT(*) AS `cnt` FROM `bookings` WHERE `customer_id` = ? AND `tenant_id` = ? AND DATE(`start_datetime`) = ? AND `status` IN (?, ?)',
                    [$customerId, $tenant['id'], $startDt->format('Y-m-d'), 'confirmed', 'rescheduled']
                );
                $existingCount = (int) ($countRows[0]['cnt'] ?? 0);

                if ($existingCount >= $maxPerDay) {
                    $pdo->rollBack();
                    return Response::json([
                        'error'   => 'max_bookings_exceeded',
                        'message' => __('booking.api.max_bookings_exceeded'),
                    ], 422);
                }
            }

            // Create booking via BookingService (handles consent evidence)
            $bookingData = [
                'tenant_id'       => $tenant['id'],
                'customer_id'     => $customerId,
                'booking_pattern' => $tenant['booking_pattern'] ?? 'timeslot',
                'start_datetime'  => $startDt->format('Y-m-d H:i:s'),
                'end_datetime'    => $endDt->format('Y-m-d H:i:s'),
                'source'          => 'web',
            ];

            if ($serviceId) {
                $bookingData['service_id'] = $serviceId;
            }
            if ($resolvedStaffId) {
                $bookingData['staff_id'] = $resolvedStaffId;
            }

            $notes = trim($input['notes'] ?? '');
            if ($notes !== '') {
                $bookingData['notes'] = $notes;
            }

            $customFields = $input['custom_fields'] ?? null;
            if ($customFields && is_array($customFields)) {
                $bookingData['custom_field_data'] = $customFields;
            }

            // Capture customer timezone from browser (Intl.DateTimeFormat)
            $customerTimezone = trim($input['customer_timezone'] ?? '');
            if ($customerTimezone !== '' && @timezone_open($customerTimezone)) {
                $bookingData['customer_timezone'] = $customerTimezone;
            }

            $result = BookingService::createBooking($bookingData, $tenant, $consentGiven);

            $pdo->commit();

            // After commit: update customer stats
            Database::execute(
                'UPDATE `customers` SET `booking_count` = `booking_count` + 1, `last_booking_at` = NOW() WHERE `id` = ?',
                [$customerId]
            );

            // Build confirmation response
            $serviceName = null;
            if ($serviceId) {
                $svc = Database::query('SELECT `name` FROM `services` WHERE `id` = ? LIMIT 1', [$serviceId]);
                $serviceName = $svc[0]['name'] ?? null;
            }

            $staffName = null;
            if ($resolvedStaffId) {
                $stf = Database::query('SELECT `name` FROM `staff` WHERE `id` = ? LIMIT 1', [$resolvedStaffId]);
                $staffName = $stf[0]['name'] ?? null;
            }

            // After commit: dispatch confirmation email (never inside transaction — PRD §III)
            $emailSent = false;
            if (Mailer::isConfigured()) {
                try {
                    $emailResult = Mailer::sendBookingConfirmation(
                        $customerEmail,
                        $customerName,
                        [
                            'date'           => $startDt->format('Y-m-d'),
                            'formatted_date' => Locale::dateLong($startDt),
                            'time'           => $startDt->format('H:i'),
                            'end_time'       => $endDt->format('H:i'),
                            'duration'       => $serviceDuration,
                        ],
                        $serviceName,
                        $staffName,
                        $tenant['name'],
                        $tenant['id'],
                        $result['id'],
                        $tenant['brand_color'] ?? '#2563EB',
                    );
                    // Customer-facing flag: true only when email reached a real inbox
                    $emailSent = $emailResult['sent'] && Mailer::isProductionSmtp();
                } catch (\Throwable $e) {
                    Logger::error('Confirmation email dispatch failed', [
                        'booking' => $result['id'],
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            return Response::json([
                'booking' => [
                    'id'               => $result['id'],
                    'service'          => $serviceName,
                    'staff'            => $staffName,
                    'date'             => $startDt->format('Y-m-d'),
                    'time'             => $startDt->format('H:i'),
                    'end_time'         => $endDt->format('H:i'),
                    'duration'         => $serviceDuration,
                    'consent_recorded' => $result['consent_recorded'],
                    'email_sent'       => $emailSent,
                ],
            ], 201);

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('Booking creation failed', [
                'tenant' => $tenant['slug'] ?? $slug,
                'error'  => $e->getMessage(),
            ]);

            return Response::json([
                'error'   => 'booking_failed',
                'message' => __('booking.api.booking_failed'),
            ], 500);
        }
    }

    // ── Resource-pattern booking creation ──

    /**
     * Create a resource-pattern booking (hotel room, meeting room, etc.).
     *
     * Validates resource availability for the requested date range,
     * checks guest capacity, and creates the booking with date-based datetimes.
     */
    private function createResourceBooking(
        array $tenant,
        array $input,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
    ): Response {
        $resourceId = $input['resource_id'] ?? null;
        $checkIn = $input['check_in'] ?? null;
        $checkOut = $input['check_out'] ?? null;
        $guestCount = (int) ($input['guest_count'] ?? 1);
        $consentGiven = (bool) ($input['consent_given'] ?? false);

        if (!$resourceId) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.resource_required')], 422);
        }
        if (!$checkIn || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn)) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.check_in_required')], 422);
        }
        if (!$checkOut || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.check_out_required')], 422);
        }
        if ($guestCount < 1) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.guest_count_invalid')], 422);
        }

        // Check availability via ResourceCalculator
        $availability = ResourceCalculator::checkAvailability(
            $tenant,
            $resourceId,
            $checkIn,
            $checkOut,
            $guestCount,
        );

        if (!$availability['available']) {
            return Response::json([
                'error'   => $availability['error'],
                'message' => __('booking.api.resource_unavailable'),
            ], 409);
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
            $lockStmt->execute([$tenant['id'], $resourceId, $checkOut . ' 00:00:00', $checkIn . ' 00:00:00']);

            // Re-check availability inside the lock
            $recheck = ResourceCalculator::checkAvailability($tenant, $resourceId, $checkIn, $checkOut, $guestCount);
            if (!$recheck['available']) {
                $pdo->rollBack();
                return Response::json([
                    'error'   => 'resource_unavailable',
                    'message' => __('booking.api.resource_unavailable'),
                ], 409);
            }

            // Find or create customer
            $customerId = CustomerService::findOrCreate(
                $tenant['id'],
                $customerName,
                $customerEmail,
                $customerPhone,
            );

            // Build booking data
            $bookingData = [
                'tenant_id'       => $tenant['id'],
                'customer_id'     => $customerId,
                'booking_pattern' => 'resource',
                'resource_id'     => $resourceId,
                'start_datetime'  => $checkIn . ' 00:00:00',
                'end_datetime'    => $checkOut . ' 00:00:00',
                'party_size'      => $guestCount,
                'source'          => 'web',
            ];

            $notes = trim($input['notes'] ?? '');
            if ($notes !== '') {
                $bookingData['notes'] = $notes;
            }

            $customFields = $input['custom_fields'] ?? null;
            if ($customFields && is_array($customFields)) {
                $bookingData['custom_field_data'] = $customFields;
            }

            $customerTimezone = trim($input['customer_timezone'] ?? '');
            if ($customerTimezone !== '' && @timezone_open($customerTimezone)) {
                $bookingData['customer_timezone'] = $customerTimezone;
            }

            $result = BookingService::createBooking($bookingData, $tenant, $consentGiven);

            $pdo->commit();

            // After commit: update customer stats
            Database::execute(
                'UPDATE `customers` SET `booking_count` = `booking_count` + 1, `last_booking_at` = NOW() WHERE `id` = ?',
                [$customerId]
            );

            // After commit: dispatch confirmation email
            $emailSent = false;
            if (Mailer::isConfigured()) {
                try {
                    $resourceName = $availability['resource']['name'] ?? null;
                    $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
                    $checkInDt = new \DateTimeImmutable($checkIn, $tz);
                    $checkOutDt = new \DateTimeImmutable($checkOut, $tz);

                    $emailResult = Mailer::sendBookingConfirmation(
                        $customerEmail,
                        $customerName,
                        [
                            'date'           => $checkIn,
                            'formatted_date' => Locale::dateLong($checkInDt) . ' – ' . Locale::dateLong($checkOutDt),
                            'time'           => '',
                            'end_time'       => '',
                            'duration'       => $availability['nights'] . ' ' . ($availability['nights'] === 1 ? 'night' : 'nights'),
                        ],
                        $resourceName,
                        null, // no staff
                        $tenant['name'],
                        $tenant['id'],
                        $result['id'],
                        $tenant['brand_color'] ?? '#2563EB',
                    );
                    $emailSent = $emailResult['sent'] && Mailer::isProductionSmtp();
                } catch (\Throwable $e) {
                    Logger::error('Resource confirmation email dispatch failed', [
                        'booking' => $result['id'],
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            return Response::json([
                'booking' => [
                    'id'               => $result['id'],
                    'resource'         => $availability['resource']['name'] ?? null,
                    'check_in'         => $checkIn,
                    'check_out'        => $checkOut,
                    'nights'           => $availability['nights'],
                    'guest_count'      => $guestCount,
                    'total'            => $availability['total'],
                    'consent_recorded' => $result['consent_recorded'],
                    'email_sent'       => $emailSent,
                ],
            ], 201);

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('Resource booking creation failed', [
                'tenant' => $tenant['slug'] ?? '',
                'error'  => $e->getMessage(),
            ]);

            return Response::json([
                'error'   => 'booking_failed',
                'message' => __('booking.api.booking_failed'),
            ], 500);
        }
    }

    // ── Capacity-pattern read endpoints ──

    /**
     * GET /api/{slug}/capacity/available-dates — dates with capacity remaining.
     */
    public function capacityAvailableDates(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $year = (int) ($request->string('year') ?: date('Y'));
        $month = (int) ($request->string('month') ?: date('n'));
        $partySize = max(1, (int) ($request->string('party_size') ?: 1));

        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2040) {
            return Response::json(['error' => 'invalid_month'], 400);
        }

        $monthStr = sprintf('%04d-%02d', $year, $month);
        $result = CapacityCalculator::getAvailableDates($tenant, $monthStr, $partySize);

        return Response::json($result);
    }

    /**
     * GET /api/{slug}/capacity/slots — available time slots for a date.
     */
    public function capacitySlots(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $date = $request->string('date');
        $partySize = max(1, (int) ($request->string('party_size') ?: 1));

        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Response::json(['error' => 'invalid_date'], 400);
        }

        $result = CapacityCalculator::getAvailableSlots($tenant, $date, $partySize);

        return Response::json($result);
    }

    // ── Capacity-pattern booking creation ──

    /**
     * Create a capacity-pattern booking (restaurant, escape room, group class).
     *
     * Validates slot availability for the requested party size,
     * checks capacity limits, and creates the booking.
     */
    private function createCapacityBooking(
        array $tenant,
        array $input,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
    ): Response {
        $slotId    = $input['slot_id'] ?? null;
        $date      = $input['date'] ?? null;
        $partySize = (int) ($input['party_size'] ?? 1);
        $consentGiven = (bool) ($input['consent_given'] ?? false);

        if (!$slotId) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.slot_required')], 422);
        }
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.date_required')], 422);
        }
        if ($partySize < 1) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.party_size_invalid')], 422);
        }

        // Pre-lock check for fast feedback
        $availability = CapacityCalculator::checkSlotAvailability($tenant, $slotId, $date, $partySize);
        if (!$availability['available']) {
            $errorKey = $availability['error'] ?? 'capacity_exceeded';
            $messageKey = match ($errorKey) {
                'party_too_small' => 'booking.api.party_too_small',
                default => 'booking.api.capacity_exceeded',
            };
            return Response::json([
                'error'   => $errorKey,
                'message' => __($messageKey),
            ], 409);
        }

        // Load slot details for start/end time
        $slotRows = Database::query(
            'SELECT `start_time`, `end_time`, `label` FROM `capacity_slots` WHERE `id` = ? AND `tenant_id` = ?',
            [$slotId, $tenant['id']]
        );
        if (empty($slotRows)) {
            return Response::json(['error' => 'slot_not_found', 'message' => __('booking.api.slot_required')], 422);
        }
        $slot = $slotRows[0];

        // Double-booking prevention: transaction + FOR UPDATE lock
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock existing capacity bookings for this slot time on this date
            $startDt = $date . ' ' . $slot['start_time'];
            $lockStmt = $pdo->prepare(
                'SELECT `id` FROM `bookings`
                 WHERE `tenant_id` = ? AND `booking_pattern` = \'capacity\'
                 AND `start_datetime` = ? AND `status` IN (\'confirmed\', \'rescheduled\')
                 FOR UPDATE'
            );
            $lockStmt->execute([$tenant['id'], $startDt]);

            // Re-check availability inside the lock
            $recheck = CapacityCalculator::checkSlotAvailability($tenant, $slotId, $date, $partySize);
            if (!$recheck['available']) {
                $pdo->rollBack();
                return Response::json([
                    'error'   => 'capacity_exceeded',
                    'message' => __('booking.api.capacity_exceeded'),
                ], 409);
            }

            // Find or create customer
            $customerId = CustomerService::findOrCreate(
                $tenant['id'],
                $customerName,
                $customerEmail,
                $customerPhone,
            );

            // Build booking data
            $endDt = $date . ' ' . $slot['end_time'];
            $bookingData = [
                'tenant_id'       => $tenant['id'],
                'customer_id'     => $customerId,
                'booking_pattern' => 'capacity',
                'start_datetime'  => $startDt,
                'end_datetime'    => $endDt,
                'party_size'      => $partySize,
                'source'          => 'web',
            ];

            $notes = trim($input['notes'] ?? '');
            if ($notes !== '') {
                $bookingData['notes'] = $notes;
            }

            $customFields = $input['custom_fields'] ?? null;
            if ($customFields && is_array($customFields)) {
                $bookingData['custom_field_data'] = $customFields;
            }

            $customerTimezone = trim($input['customer_timezone'] ?? '');
            if ($customerTimezone !== '' && @timezone_open($customerTimezone)) {
                $bookingData['customer_timezone'] = $customerTimezone;
            }

            $result = BookingService::createBooking($bookingData, $tenant, $consentGiven);

            $pdo->commit();

            // After commit: update customer stats
            Database::execute(
                'UPDATE `customers` SET `booking_count` = `booking_count` + 1, `last_booking_at` = NOW() WHERE `id` = ?',
                [$customerId]
            );

            // After commit: dispatch confirmation email
            $emailSent = false;
            if (Mailer::isConfigured()) {
                try {
                    $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
                    $slotDt = new \DateTimeImmutable($startDt, $tz);
                    $slotEndDt = new \DateTimeImmutable($endDt, $tz);

                    $emailResult = Mailer::sendBookingConfirmation(
                        $customerEmail,
                        $customerName,
                        [
                            'date'           => $date,
                            'formatted_date' => Locale::dateLong($slotDt),
                            'time'           => substr($slot['start_time'], 0, 5),
                            'end_time'       => substr($slot['end_time'], 0, 5),
                            'duration'       => $partySize . ' ' . ($partySize === 1 ? __('booking.capacity.guest') : __('booking.capacity.guests')),
                        ],
                        $slot['label'] ?? null, // serviceName → slot label
                        null, // no staff
                        $tenant['name'],
                        $tenant['id'],
                        $result['id'],
                        $tenant['brand_color'] ?? '#2563EB',
                    );
                    $emailSent = $emailResult['sent'] && Mailer::isProductionSmtp();
                } catch (\Throwable $e) {
                    Logger::error('Capacity confirmation email dispatch failed', [
                        'booking' => $result['id'],
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            return Response::json([
                'booking' => [
                    'id'               => $result['id'],
                    'date'             => $date,
                    'time'             => substr($slot['start_time'], 0, 5),
                    'end_time'         => substr($slot['end_time'], 0, 5),
                    'label'            => $slot['label'],
                    'party_size'       => $partySize,
                    'consent_recorded' => $result['consent_recorded'],
                    'email_sent'       => $emailSent,
                ],
            ], 201);

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('Capacity booking creation failed', [
                'tenant' => $tenant['slug'] ?? '',
                'error'  => $e->getMessage(),
            ]);

            return Response::json([
                'error'   => 'booking_failed',
                'message' => __('booking.api.booking_failed'),
            ], 500);
        }
    }

    // ── Event-pattern read endpoints ──

    /**
     * GET /api/{slug}/events
     *
     * Returns upcoming events with remaining spots, sorted chronologically.
     * Recurring events are expanded from RRULE into concrete instances.
     */
    public function events(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $this->resolveLocale($tenant, $request);

        $result = EventCalculator::getUpcomingEvents($tenant);

        return Response::json($result);
    }

    /**
     * GET /api/{slug}/events/{id}
     *
     * Returns a single event detail with availability info.
     * For recurring events, pass ?date=YYYY-MM-DD to select the instance.
     */
    public function eventDetail(Request $request): Response
    {
        $slug = $request->getAttribute('slug');
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            return Response::json(['error' => 'tenant_not_found'], 404);
        }

        $this->resolveLocale($tenant, $request);

        $eventId = $request->getAttribute('id');
        $date = $request->string('date') ?: null;

        $result = EventCalculator::getEventDetail($tenant, $eventId, $date);

        if (!$result['event']) {
            return Response::json(['error' => 'event_not_found'], 404);
        }

        return Response::json($result);
    }

    // ── Event-pattern booking creation ──

    /**
     * Create an event-pattern booking.
     *
     * Validates event availability and spot count, handles waitlist behavior.
     * If event is full and allows waitlist, creates booking with status 'waitlisted'.
     */
    private function createEventBooking(
        array $tenant,
        array $input,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
    ): Response {
        $eventId   = $input['event_id'] ?? null;
        $date      = $input['date'] ?? null;
        $spotCount = (int) ($input['spot_count'] ?? 1);
        $consentGiven = (bool) ($input['consent_given'] ?? false);

        if (!$eventId) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.event_required')], 422);
        }
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.date_required')], 422);
        }
        if ($spotCount < 1) {
            return Response::json(['error' => 'validation', 'message' => __('booking.api.spot_count_invalid')], 422);
        }

        // Pre-lock check for fast feedback
        $availability = EventCalculator::checkAvailability($tenant, $eventId, $date, $spotCount);
        if (!$availability['available']) {
            $errorKey = $availability['error'] ?? 'event_full';
            $messageKey = match ($errorKey) {
                'spot_count_too_few'  => 'booking.api.spot_count_too_few',
                'spot_count_too_many' => 'booking.api.spot_count_too_many',
                'waitlist_full'       => 'booking.api.waitlist_full',
                'instance_cancelled'  => 'booking.api.event_cancelled',
                default               => 'booking.api.event_full',
            };
            return Response::json([
                'error'   => $errorKey,
                'message' => __($messageKey),
            ], 409);
        }

        $isWaitlisted = $availability['waitlisted'];

        // Load event details for datetime
        $eventRows = Database::query(
            'SELECT * FROM `events` WHERE `id` = ? AND `tenant_id` = ? AND `is_active` = 1',
            [$eventId, $tenant['id']]
        );
        if (empty($eventRows)) {
            return Response::json(['error' => 'event_not_found', 'message' => __('booking.api.event_required')], 422);
        }
        $event = $eventRows[0];

        // Build start/end datetime from event time + instance date
        $tz = new \DateTimeZone($tenant['timezone'] ?? 'UTC');
        $origStart = new \DateTimeImmutable($event['start_datetime'], $tz);
        $origEnd = new \DateTimeImmutable($event['end_datetime'], $tz);
        $startDt = $date . ' ' . $origStart->format('H:i:s');
        $endDt = $date . ' ' . $origEnd->format('H:i:s');

        // Double-booking prevention: transaction + FOR UPDATE lock
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock existing event bookings for this event+date
            $lockStmt = $pdo->prepare(
                'SELECT `id` FROM `bookings`
                 WHERE `tenant_id` = ? AND `event_id` = ? AND `booking_pattern` = \'event\'
                 AND DATE(`start_datetime`) = ? AND `status` IN (\'confirmed\', \'rescheduled\', \'waitlisted\')
                 FOR UPDATE'
            );
            $lockStmt->execute([$tenant['id'], $eventId, $date]);

            // Re-check availability inside the lock
            $recheck = EventCalculator::checkAvailability($tenant, $eventId, $date, $spotCount);
            if (!$recheck['available']) {
                $pdo->rollBack();
                $errorKey = $recheck['error'] ?? 'event_full';
                $messageKey = match ($errorKey) {
                    'waitlist_full' => 'booking.api.waitlist_full',
                    default => 'booking.api.event_full',
                };
                return Response::json([
                    'error'   => $errorKey,
                    'message' => __($messageKey),
                ], 409);
            }

            $isWaitlisted = $recheck['waitlisted'];

            // Find or create customer
            $customerId = CustomerService::findOrCreate(
                $tenant['id'],
                $customerName,
                $customerEmail,
                $customerPhone,
            );

            // Build booking data
            $bookingData = [
                'tenant_id'       => $tenant['id'],
                'customer_id'     => $customerId,
                'booking_pattern' => 'event',
                'event_id'        => $eventId,
                'start_datetime'  => $startDt,
                'end_datetime'    => $endDt,
                'party_size'      => $spotCount,
                'status'          => $isWaitlisted ? 'waitlisted' : 'confirmed',
                'source'          => 'web',
            ];

            $notes = trim($input['notes'] ?? '');
            if ($notes !== '') {
                $bookingData['notes'] = $notes;
            }

            $customFields = $input['custom_fields'] ?? null;
            if ($customFields && is_array($customFields)) {
                $bookingData['custom_field_data'] = $customFields;
            }

            $customerTimezone = trim($input['customer_timezone'] ?? '');
            if ($customerTimezone !== '' && @timezone_open($customerTimezone)) {
                $bookingData['customer_timezone'] = $customerTimezone;
            }

            $result = BookingService::createBooking($bookingData, $tenant, $consentGiven);

            $pdo->commit();

            // After commit: update customer stats
            Database::execute(
                'UPDATE `customers` SET `booking_count` = `booking_count` + 1, `last_booking_at` = NOW() WHERE `id` = ?',
                [$customerId]
            );

            // After commit: dispatch confirmation email (different for waitlisted)
            $emailSent = false;
            if (Mailer::isConfigured()) {
                try {
                    $slotDt = new \DateTimeImmutable($startDt, $tz);
                    $slotEndDt = new \DateTimeImmutable($endDt, $tz);

                    $emailBookingData = [
                        'date'           => $date,
                        'formatted_date' => Locale::dateLong($slotDt),
                        'time'           => $origStart->format('H:i'),
                        'end_time'       => $origEnd->format('H:i'),
                        'duration'       => $spotCount . ' ' . ($spotCount === 1 ? __('booking.event.spot') : __('booking.event.spots')),
                    ];

                    if ($isWaitlisted) {
                        $emailResult = Mailer::sendWaitlistConfirmation(
                            $customerEmail,
                            $customerName,
                            $emailBookingData,
                            $event['name'],
                            $tenant['name'],
                            $tenant['id'],
                            $result['id'],
                            $tenant['brand_color'] ?? '#2563EB',
                        );
                    } else {
                        $emailResult = Mailer::sendBookingConfirmation(
                            $customerEmail,
                            $customerName,
                            $emailBookingData,
                            $event['name'],
                            null, // no staff
                            $tenant['name'],
                            $tenant['id'],
                            $result['id'],
                            $tenant['brand_color'] ?? '#2563EB',
                        );
                    }
                    $emailSent = $emailResult['sent'] && Mailer::isProductionSmtp();
                } catch (\Throwable $e) {
                    Logger::error('Event confirmation email dispatch failed', [
                        'booking' => $result['id'],
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            return Response::json([
                'booking' => [
                    'id'               => $result['id'],
                    'event_name'       => $event['name'],
                    'date'             => $date,
                    'time'             => $origStart->format('H:i'),
                    'end_time'         => $origEnd->format('H:i'),
                    'spot_count'       => $spotCount,
                    'status'           => $isWaitlisted ? 'waitlisted' : 'confirmed',
                    'waitlisted'       => $isWaitlisted,
                    'consent_recorded' => $result['consent_recorded'],
                    'email_sent'       => $emailSent,
                ],
            ], 201);

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('Event booking creation failed', [
                'tenant' => $tenant['slug'] ?? '',
                'error'  => $e->getMessage(),
            ]);

            return Response::json([
                'error'   => 'booking_failed',
                'message' => __('booking.api.booking_failed'),
            ], 500);
        }
    }

}
