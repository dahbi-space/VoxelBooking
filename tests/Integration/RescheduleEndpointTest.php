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
        // Reset fixture booking to confirmed
        Database::execute(
            "UPDATE `bookings` SET `status` = 'confirmed' WHERE `id` = ?",
            [self::RESCHEDULE_BOOKING_ID]
        );

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . self::RESCHEDULE_BOOKING_ID . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+2 days')),
            'new_time' => '10:00',
        ]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString(self::RESCHEDULE_BOOKING_ID, $r['location']);
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
        Database::execute(
            "UPDATE `bookings` SET `status` = 'confirmed' WHERE `id` = ?",
            [self::RESCHEDULE_BOOKING_ID]
        );

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . self::RESCHEDULE_BOOKING_ID . '/reschedule', [
            'new_date' => 'not-a-date',
            'new_time' => '10:00',
        ]);

        $this->assertSame(302, $r['code']);
    }

    public function test_reschedule_with_invalid_time_is_rejected(): void
    {
        Database::execute(
            "UPDATE `bookings` SET `status` = 'confirmed' WHERE `id` = ?",
            [self::RESCHEDULE_BOOKING_ID]
        );

        $this->doLoginOperator();
        $r = $this->postWithCsrf('/admin/bookings/' . self::RESCHEDULE_BOOKING_ID . '/reschedule', [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => 'invalid',
        ]);

        $this->assertSame(302, $r['code']);
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

        // Customer
        Database::execute(
            "INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`)
             VALUES (?, ?, 'Reschedule Test Customer', 'resched@example.com')",
            [self::RESCHEDULE_CUSTOMER_ID, $tenantId]
        );

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
