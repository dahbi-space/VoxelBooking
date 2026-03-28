<?php

declare(strict_types=1);

namespace App\Controllers\Booking;

use App\Engine\Database;
use App\Engine\Locale;
use App\Engine\TimeSlotCalculator;
use App\Engine\BookingService;
use App\Engine\Ulid;
use App\Engine\AuditLog;
use App\Engine\Logger;
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
            'SELECT `id`, `name`, `description`, `duration_minutes`, `price`, `price_label`, `category`
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
                    array_map(fn($s) => ['time' => $s['time'], 'staff_id' => $s['staff_id']], $availResult['slots']),
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
            $customerId = $this->findOrCreateCustomer(
                $tenant['id'],
                $customerName,
                $customerEmail,
                $customerPhone,
            );

            // Create booking via BookingService (handles consent evidence)
            $bookingData = [
                'tenant_id'       => $tenant['id'],
                'customer_id'     => $customerId,
                'booking_pattern' => 'timeslot',
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

    /**
     * Find or create a customer for a tenant.
     */
    private function findOrCreateCustomer(
        string $tenantId,
        string $name,
        string $email,
        string $phone,
    ): string {
        $existing = Database::query(
            'SELECT `id` FROM `customers` WHERE `tenant_id` = ? AND `email` = ? LIMIT 1',
            [$tenantId, $email]
        );

        if (!empty($existing)) {
            Database::execute(
                'UPDATE `customers` SET `name` = ?, `phone` = ?, `updated_at` = NOW() WHERE `id` = ?',
                [$name, $phone ?: null, $existing[0]['id']]
            );
            return $existing[0]['id'];
        }

        $customerId = Ulid::generate();
        Database::execute(
            'INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`, `phone`) VALUES (?, ?, ?, ?, ?)',
            [$customerId, $tenantId, $name, $email, $phone ?: null]
        );

        return $customerId;
    }
}
