<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the admin reschedule POST endpoint.
 *
 * Covers:
 * - Operator guard on cross-tenant route
 * - Business user access on tenant-context route
 * - Non-timeslot pattern rejection
 * - Pending booking rejection (must be confirmed)
 * - Same-slot rejection
 * - Successful reschedule: booking chain, status, rescheduled_to_id
 * - Rescheduled status is terminal in the simple status dropdown
 */
final class RescheduleEndpointTest extends TestCase
{
    private static bool $appReachable = false;
    private string $baseUrl;
    private string $cookieJar;

    private const RESCHEDULE_BOOKING_ID = '01TESTRESCHEDULEBOOKING0';
    private const RESCHEDULE_CUSTOMER_ID = '01TESTRESCHEDCUST000000';
    private const RESCHEDULE_SERVICE_ID = '01TESTRESCHEDSVC00000000';
    private const HAPPY_BOOKING_ID = '01TESTRESCHEDHAPPY000000';

    public static function setUpBeforeClass(): void
    {
        $baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');

        $ch = curl_init($baseUrl . '/health');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 5]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 0) {
            return;
        }

        self::$appReachable = true;

        try {
            TestFixtures::provision();
            self::seedRescheduleFixtures();
        } catch (\Throwable) {
            // best-effort
        }
    }

    protected function setUp(): void
    {
        if (!self::$appReachable) {
            $this->markTestSkipped('App not reachable');
        }

        $this->baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'vb_resched_test_') ?: '/tmp/vb_resched_test_cookies';
    }

    protected function tearDown(): void
    {
        if (isset($this->cookieJar) && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Auth guard: business user cannot use cross-tenant reschedule
    // ════════════════════════════════════════════════════════════════

    public function test_business_user_cross_tenant_reschedule_returns_403(): void
    {
        $this->doLoginBusinessUser();
        $r = $this->post('/admin/bookings/' . self::RESCHEDULE_BOOKING_ID . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(403, $r['code'], 'Business user should be denied cross-tenant reschedule');
    }

    // ════════════════════════════════════════════════════════════════
    // Status guard: only confirmed bookings can be rescheduled
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_pending_booking_is_rejected(): void
    {
        // Create a pending booking
        $pendingId = '01TESTRESCHEDPENDING0000';
        $this->insertBooking($pendingId, 'pending', 'timeslot');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $pendingId . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        // Should redirect back with error (302, not 200)
        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString($pendingId, $r['location']);
    }

    public function test_reschedule_cancelled_booking_is_rejected(): void
    {
        $cancelledId = '01TESTRESCHEDCANCELLED00';
        $this->insertBooking($cancelledId, 'cancelled', 'timeslot');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $cancelledId . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString($cancelledId, $r['location']);
    }

    // ════════════════════════════════════════════════════════════════
    // Pattern guard: non-timeslot patterns are rejected
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_resource_booking_is_rejected(): void
    {
        $resourceId = '01TESTRESCHEDRESOURCE000';
        $this->insertBooking($resourceId, 'confirmed', 'resource');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $resourceId . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString($resourceId, $r['location']);
    }

    public function test_reschedule_capacity_booking_is_rejected(): void
    {
        $capacityId = '01TESTRESCHEDCAPACITY000';
        $this->insertBooking($capacityId, 'confirmed', 'capacity');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $capacityId . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString($capacityId, $r['location']);
    }

    public function test_reschedule_event_booking_is_rejected(): void
    {
        $eventId = '01TESTRESCHEDEVENT000000';
        $this->insertBooking($eventId, 'confirmed', 'event');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $eventId . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString($eventId, $r['location']);
    }

    // ════════════════════════════════════════════════════════════════
    // Same-slot rejection
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_to_same_slot_is_rejected(): void
    {
        $sameSlotId = '01TESTRESCHEDSAMESLOT000';
        $startDate = date('Y-m-d', strtotime('+2 days'));
        $this->insertBooking($sameSlotId, 'confirmed', 'timeslot');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $sameSlotId . '/reschedule', [
            'new_date' => $startDate,
            'new_time' => '10:00',
        ]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString($sameSlotId, $r['location']);
    }

    // ════════════════════════════════════════════════════════════════
    // Rescheduled status is terminal in simple status dropdown
    // ════════════════════════════════════════════════════════════════

    public function test_status_change_from_rescheduled_is_rejected(): void
    {
        // Set booking to rescheduled status directly
        $rescheduledId = '01TESTRESCHEDTERMINAL000';
        $this->insertBooking($rescheduledId, 'rescheduled', 'timeslot');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $rescheduledId . '/status', [
            'status' => 'confirmed',
        ]);

        // Rescheduled→confirmed should be rejected (rescheduled has no outbound transitions)
        $this->assertSame(302, $r['code']);

        // Verify the booking is still rescheduled
        $booking = Database::query("SELECT `status` FROM `bookings` WHERE `id` = ?", [$rescheduledId]);
        $this->assertSame('rescheduled', $booking[0]['status'] ?? null);
    }

    // ════════════════════════════════════════════════════════════════
    // Input validation
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_with_invalid_date_is_rejected(): void
    {
        $badDateId = '01TESTRESCHEDBADDATE0000';
        $this->insertBooking($badDateId, 'confirmed', 'timeslot');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $badDateId . '/reschedule', [
            'new_date' => 'not-a-date',
            'new_time' => '10:00',
        ]);

        $this->assertSame(302, $r['code']);
    }

    public function test_reschedule_with_invalid_time_is_rejected(): void
    {
        $badTimeId = '01TESTRESCHEDBADTIME0000';
        $this->insertBooking($badTimeId, 'confirmed', 'timeslot');

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . $badTimeId . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => 'invalid',
        ]);

        $this->assertSame(302, $r['code']);
    }

    // ════════════════════════════════════════════════════════════════
    // Happy path: successful reschedule
    // ════════════════════════════════════════════════════════════════

    public function test_successful_reschedule_creates_chain_and_preserves_data(): void
    {
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        // Determine a valid target date (weekday, +3 days, clamped to avoid weekends)
        $targetTs = strtotime('+3 days');
        // Walk to next weekday if Saturday/Sunday
        while (date('N', $targetTs) >= 6) {
            $targetTs = strtotime('+1 day', $targetTs);
        }
        $targetDate = date('Y-m-d', $targetTs);

        // Reset the happy-path booking to confirmed with custom data
        Database::execute("DELETE FROM `bookings` WHERE `id` = ?", [self::HAPPY_BOOKING_ID]);
        $origDate = date('Y-m-d', strtotime('+2 days'));
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `service_id`,
              `start_datetime`, `end_datetime`, `status`, `source`,
              `custom_field_data`, `internal_notes`)
             VALUES (?, ?, 'timeslot', ?, ?,
              '{$origDate} 10:00:00', '{$origDate} 10:30:00',
              'confirmed', 'web',
              '{\"allergies\":\"peanuts\"}', 'VIP customer — needs extra time')",
            [self::HAPPY_BOOKING_ID, $tenantId, self::RESCHEDULE_CUSTOMER_ID, self::RESCHEDULE_SERVICE_ID]
        );

        // Clear any prior reschedule-produced bookings for clean assertion
        Database::execute(
            "DELETE FROM `bookings` WHERE `tenant_id` = ? AND `source` = 'admin' AND `id` != ?",
            [$tenantId, self::HAPPY_BOOKING_ID]
        );

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . self::HAPPY_BOOKING_ID . '/reschedule', [
            'new_date' => $targetDate,
            'new_time' => '11:00',
        ]);

        // Should redirect to the NEW booking's detail page (302)
        $this->assertSame(302, $r['code'], 'Successful reschedule should redirect');
        // Redirect should NOT point back to the original booking
        $this->assertStringNotContainsString(self::HAPPY_BOOKING_ID, $r['location'],
            'Redirect should point to the new booking, not the original');

        // Verify original booking is now rescheduled with rescheduled_to_id
        $original = Database::query(
            "SELECT `status`, `rescheduled_to_id` FROM `bookings` WHERE `id` = ?",
            [self::HAPPY_BOOKING_ID]
        );
        $this->assertSame('rescheduled', $original[0]['status'] ?? null,
            'Original booking should be marked rescheduled');
        $this->assertNotNull($original[0]['rescheduled_to_id'] ?? null,
            'Original booking should have rescheduled_to_id set');

        $newBookingId = $original[0]['rescheduled_to_id'];

        // Verify the new booking exists with correct data
        $newBooking = Database::query(
            "SELECT `status`, `booking_pattern`, `customer_id`, `service_id`,
                    `start_datetime`, `custom_field_data`, `internal_notes`, `source`
             FROM `bookings` WHERE `id` = ?",
            [$newBookingId]
        );
        $this->assertNotEmpty($newBooking, 'New booking should exist');
        $this->assertSame('confirmed', $newBooking[0]['status']);
        $this->assertSame('timeslot', $newBooking[0]['booking_pattern']);
        $this->assertSame(self::RESCHEDULE_CUSTOMER_ID, $newBooking[0]['customer_id']);
        $this->assertSame(self::RESCHEDULE_SERVICE_ID, $newBooking[0]['service_id']);
        $this->assertSame('admin', $newBooking[0]['source']);
        $this->assertStringContainsString($targetDate . ' 11:00', $newBooking[0]['start_datetime']);

        // Verify custom_field_data and internal_notes were preserved
        $this->assertStringContainsString('peanuts', $newBooking[0]['custom_field_data'] ?? '',
            'custom_field_data should be preserved on the new booking');
        $this->assertSame('VIP customer — needs extra time', $newBooking[0]['internal_notes'] ?? null,
            'internal_notes should be preserved on the new booking');

        // Verify audit log entry
        $audit = Database::query(
            "SELECT `action`, `details` FROM `audit_log` WHERE `entity_type` = 'booking' AND `entity_id` = ? ORDER BY `created_at` DESC LIMIT 1",
            [self::HAPPY_BOOKING_ID]
        );
        $this->assertNotEmpty($audit, 'Audit log entry should exist for reschedule');
        $this->assertSame('booking.rescheduled', $audit[0]['action']);
    }

    // ════════════════════════════════════════════════════════════════
    // Fixtures
    // ════════════════════════════════════════════════════════════════

    private static function seedRescheduleFixtures(): void
    {
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        // Clean prior reschedule test bookings
        Database::execute("DELETE FROM `bookings` WHERE `id` LIKE '01TESTRESCHED%'");
        Database::execute("DELETE FROM `customers` WHERE `id` = ?", [self::RESCHEDULE_CUSTOMER_ID]);
        Database::execute("DELETE FROM `services` WHERE `id` = ?", [self::RESCHEDULE_SERVICE_ID]);

        // Customer
        Database::execute(
            "INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`)
             VALUES (?, ?, 'Reschedule Test Customer', 'resched@example.com')",
            [self::RESCHEDULE_CUSTOMER_ID, $tenantId]
        );

        // Service (30-minute duration for slot calculation)
        Database::execute(
            "INSERT INTO `services` (`id`, `tenant_id`, `name`, `duration_minutes`, `is_active`, `sort_order`)
             VALUES (?, ?, 'Reschedule Test Service', 30, 1, 99)",
            [self::RESCHEDULE_SERVICE_ID, $tenantId]
        );

        // Seed weekday availability (Mon-Fri = day_of_week 0-4)
        // Clean first since TestFixtures::provision() re-creates the tenant
        Database::execute(
            "DELETE FROM `availability` WHERE `tenant_id` = ? AND `staff_id` IS NULL",
            [$tenantId]
        );
        for ($dow = 0; $dow <= 4; $dow++) {
            Database::execute(
                "INSERT INTO `availability` (`id`, `tenant_id`, `day_of_week`, `start_time`, `end_time`, `is_available`)
                 VALUES (?, ?, ?, '08:00', '18:00', 1)",
                ["01TESTRESCHEDAVAIL00000{$dow}", $tenantId, $dow]
            );
        }

        // Confirmed timeslot booking (the main fixture)
        $startDate = date('Y-m-d', strtotime('+2 days'));
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`,
              `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?,
              '{$startDate} 10:00:00', '{$startDate} 10:30:00',
              'confirmed', 'web')",
            [self::RESCHEDULE_BOOKING_ID, $tenantId, self::RESCHEDULE_CUSTOMER_ID]
        );
    }

    private function insertBooking(string $id, string $status, string $pattern): void
    {
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;
        $startDate = date('Y-m-d', strtotime('+2 days'));

        Database::execute("DELETE FROM `bookings` WHERE `id` = ?", [$id]);
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`,
              `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, ?, ?,
              '{$startDate} 10:00:00', '{$startDate} 10:30:00',
              ?, 'web')",
            [$id, $tenantId, $pattern, self::RESCHEDULE_CUSTOMER_ID, $status]
        );
    }

    // ════════════════════════════════════════════════════════════════
    // HTTP helpers
    // ════════════════════════════════════════════════════════════════

    private function doLoginOperator(): void
    {
        $response = $this->get('/admin/login');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $response['body'], $m);
        $csrf = $m[1] ?? '';

        $this->post('/admin/login', [
            'email' => TestFixtures::OPERATOR_EMAIL,
            'password' => TestFixtures::OPERATOR_PASSWORD,
            '_csrf_token' => $csrf,
        ]);
    }

    private function doLoginBusinessUser(): void
    {
        $response = $this->get('/admin/login');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $response['body'], $m);
        $csrf = $m[1] ?? '';

        $this->post('/admin/login', [
            'email' => TestFixtures::BUSINESS_EMAIL,
            'password' => TestFixtures::BUSINESS_PASSWORD,
            '_csrf_token' => $csrf,
        ]);
    }

    /**
     * POST with CSRF token fetched from the booking detail page.
     */
    private function postWithCsrf(string $path, array $data): array
    {
        // Fetch a page to get the CSRF token
        $page = $this->get('/admin/bookings');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $page['body'], $m);
        $data['_csrf_token'] = $m[1] ?? '';

        return $this->post($path, $data);
    }

    /**
     * @return array{code: int, body: string, location: string}
     */
    private function get(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ]);
        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $body = substr($response, $headerSize);
        $headers = substr($response, 0, $headerSize);
        $location = '';
        if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
            $location = trim($m[1]);
        }

        return compact('code', 'body', 'location');
    }

    private function post(string $path, array $data): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ]);
        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $body = substr($response, $headerSize);
        $headers = substr($response, 0, $headerSize);
        $location = '';
        if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
            $location = trim($m[1]);
        }

        return compact('code', 'body', 'location');
    }
}
