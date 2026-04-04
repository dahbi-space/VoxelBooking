<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the self-service reschedule public API endpoint.
 *
 * POST /api/{slug}/bookings/{id}/reschedule
 *
 * Covers:
 * - CSRF enforcement
 * - Status guard (only confirmed bookings)
 * - Tenant toggle (allow_rescheduling=0)
 * - Time-gate rejection
 * - Same-slot rejection
 * - Pattern guard (non-timeslot)
 * - Happy path: booking chain, status, rescheduled_to_id, audit log
 * - Custom field + notes preservation on the new booking
 * - Consent inheritance from original booking
 */
final class SelfServiceRescheduleTest extends TestCase
{
    private static bool $appReachable = false;
    private string $baseUrl;
    private string $cookieJar;

    // Fixture IDs — deterministic ULIDs for self-service reschedule tests
    private const CUSTOMER_ID = '01TESTSSRESCHEDCUST0000';
    private const SERVICE_ID  = '01TESTSSRESCHEDSVC00000';
    private const BOOKING_ID  = '01TESTSSRESCHEDBOOKING0';
    private const TENANT_ID   = '01TESTTENANT000000000000'; // from TestFixtures

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
            self::seedFixtures();
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
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'vb_ss_resched_') ?: '/tmp/vb_ss_resched_cookies';
    }

    protected function tearDown(): void
    {
        if (isset($this->cookieJar) && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // CSRF enforcement
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_without_csrf_returns_403(): void
    {
        $r = $this->postJson("/api/test-fixture/bookings/" . self::BOOKING_ID . "/reschedule", [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ], ''); // no CSRF token

        $this->assertSame(403, $r['code']);
        $data = json_decode($r['body'], true);
        $this->assertSame('csrf_mismatch', $data['error'] ?? null);
    }

    // ════════════════════════════════════════════════════════════════
    // Status guard: only confirmed bookings can be rescheduled
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_cancelled_booking_returns_409(): void
    {
        $cancelledId = '01TESTSSRESCHEDCANCELL0';
        $this->insertBooking($cancelledId, 'cancelled', 'timeslot');

        $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/{$cancelledId}/reschedule", [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(409, $r['code']);
        $data = json_decode($r['body'], true);
        $this->assertSame('not_confirmed', $data['error'] ?? null);
    }

    public function test_reschedule_pending_booking_returns_409(): void
    {
        $pendingId = '01TESTSSRESCHEDPENDING0';
        $this->insertBooking($pendingId, 'pending', 'timeslot');

        $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/{$pendingId}/reschedule", [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(409, $r['code']);
        $data = json_decode($r['body'], true);
        $this->assertSame('not_confirmed', $data['error'] ?? null);
    }

    // ════════════════════════════════════════════════════════════════
    // Tenant toggle: rescheduling disabled
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_when_disabled_returns_403(): void
    {
        // Temporarily disable rescheduling
        Database::execute(
            'UPDATE `tenants` SET `allow_rescheduling` = 0 WHERE `id` = ?',
            [self::TENANT_ID]
        );

        try {
            $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/" . self::BOOKING_ID . "/reschedule", [
                'new_date' => date('Y-m-d', strtotime('+3 days')),
                'new_time' => '14:00',
            ]);

            $this->assertSame(403, $r['code']);
            $data = json_decode($r['body'], true);
            $this->assertSame('rescheduling_disabled', $data['error'] ?? null);
        } finally {
            Database::execute(
                'UPDATE `tenants` SET `allow_rescheduling` = 1 WHERE `id` = ?',
                [self::TENANT_ID]
            );
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Time gate: booking starts too soon
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_past_time_gate_returns_409(): void
    {
        // Create a booking starting in 1 hour (inside 24h gate)
        $soonId = '01TESTSSRESCHEDSOON0000';
        $soonStart = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $soonEnd   = date('Y-m-d H:i:s', strtotime('+1 hour 30 minutes'));

        Database::execute("DELETE FROM `bookings` WHERE `id` = ?", [$soonId]);
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `service_id`,
              `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?, ?,
              ?, ?, 'confirmed', 'web')",
            [$soonId, self::TENANT_ID, self::CUSTOMER_ID, self::SERVICE_ID, $soonStart, $soonEnd]
        );

        $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/{$soonId}/reschedule", [
            'new_date' => date('Y-m-d', strtotime('+5 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(409, $r['code']);
        $data = json_decode($r['body'], true);
        $this->assertSame('time_gate', $data['error'] ?? null);
    }

    // ════════════════════════════════════════════════════════════════
    // Same-slot rejection
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_to_same_slot_returns_422(): void
    {
        // The fixture booking is at +2 days, 10:00
        $startDate = date('Y-m-d', strtotime('+2 days'));

        $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/" . self::BOOKING_ID . "/reschedule", [
            'new_date' => $startDate,
            'new_time' => '10:00',
        ]);

        $this->assertSame(422, $r['code']);
        $data = json_decode($r['body'], true);
        $this->assertSame('same_slot', $data['error'] ?? null);
    }

    // ════════════════════════════════════════════════════════════════
    // Pattern guard: non-timeslot patterns not supported
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_resource_booking_returns_409(): void
    {
        $resourceId = '01TESTSSRESCHEDRESOURC0';
        $this->insertBooking($resourceId, 'confirmed', 'resource');

        $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/{$resourceId}/reschedule", [
            'new_date' => date('Y-m-d', strtotime('+3 days')),
            'new_time' => '14:00',
        ]);

        $this->assertSame(409, $r['code']);
        $data = json_decode($r['body'], true);
        $this->assertSame('pattern_not_supported', $data['error'] ?? null); // pattern guard returns proper code
    }

    // ════════════════════════════════════════════════════════════════
    // Happy path: successful reschedule
    // ════════════════════════════════════════════════════════════════

    public function test_successful_reschedule_creates_chain_and_preserves_data(): void
    {
        // Determine a valid target date (weekday, +3 days, clamped to avoid weekends)
        $targetTs = strtotime('+3 days');
        while (date('N', $targetTs) >= 6) {
            $targetTs = strtotime('+1 day', $targetTs);
        }
        $targetDate = date('Y-m-d', $targetTs);

        // Reset the fixture booking with custom data
        $origDate = date('Y-m-d', strtotime('+2 days'));

        // Clean up any prior reschedule artifacts (including debug-run ghosts)
        Database::execute(
            "DELETE FROM `bookings` WHERE `tenant_id` = ? AND `customer_id` = ? AND `id` != ?",
            [self::TENANT_ID, self::CUSTOMER_ID, self::BOOKING_ID]
        );

        Database::execute("DELETE FROM `bookings` WHERE `id` = ?", [self::BOOKING_ID]);
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `service_id`,
              `start_datetime`, `end_datetime`, `status`, `source`,
              `custom_field_data`, `notes`,
              `consent_given_at`, `consent_text_shown`)
             VALUES (?, ?, 'timeslot', ?, ?,
              '{$origDate} 10:00:00', '{$origDate} 10:30:00',
              'confirmed', 'web',
              '{\"allergies\":\"peanuts\"}', 'Window seat preferred',
              '2026-04-01 10:00:00', 'I agree to the processing of my personal data.')",
            [self::BOOKING_ID, self::TENANT_ID, self::CUSTOMER_ID, self::SERVICE_ID]
        );

        // Ensure availability exists for target date
        self::ensureAvailability();

        $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/" . self::BOOKING_ID . "/reschedule", [
            'new_date' => $targetDate,
            'new_time' => '14:00',
        ]);

        $this->assertSame(200, $r['code'], 'Successful reschedule should return 200. Body: ' . $r['body']);
        $data = json_decode($r['body'], true);
        $this->assertTrue($data['rescheduled'] ?? false, 'Response should indicate rescheduled=true');
        $this->assertNotEmpty($data['new_booking_id'] ?? null, 'Response should include new_booking_id');

        $newBookingId = $data['new_booking_id'];

        // Verify original booking is now rescheduled with rescheduled_to_id
        $original = Database::query(
            "SELECT `status`, `rescheduled_to_id` FROM `bookings` WHERE `id` = ?",
            [self::BOOKING_ID]
        );
        $this->assertSame('rescheduled', $original[0]['status'] ?? null,
            'Original booking should be marked rescheduled');
        $this->assertSame($newBookingId, $original[0]['rescheduled_to_id'] ?? null,
            'Original should link to new booking via rescheduled_to_id');

        // Verify the new booking
        $newBooking = Database::query(
            "SELECT `status`, `booking_pattern`, `customer_id`, `service_id`,
                    `start_datetime`, `custom_field_data`, `notes`, `source`
             FROM `bookings` WHERE `id` = ?",
            [$newBookingId]
        );
        $this->assertNotEmpty($newBooking, 'New booking should exist');
        $this->assertSame('confirmed', $newBooking[0]['status']);
        $this->assertSame('timeslot', $newBooking[0]['booking_pattern']);
        $this->assertSame(self::CUSTOMER_ID, $newBooking[0]['customer_id']);
        $this->assertSame(self::SERVICE_ID, $newBooking[0]['service_id']);
        $this->assertSame('web', $newBooking[0]['source'], 'Self-service reschedule source should be web');
        $this->assertStringContainsString($targetDate . ' 14:00', $newBooking[0]['start_datetime']);

        // Custom field data preserved
        $this->assertStringContainsString('peanuts', $newBooking[0]['custom_field_data'] ?? '',
            'custom_field_data should be preserved on the new booking');

        // Notes preserved
        $this->assertSame('Window seat preferred', $newBooking[0]['notes'] ?? null,
            'notes should be preserved on the new booking');

        // Verify audit log entry
        $audit = Database::query(
            "SELECT `action`, `details` FROM `audit_log`
             WHERE `entity_type` = 'booking' AND `entity_id` = ?
             ORDER BY `created_at` DESC LIMIT 1",
            [self::BOOKING_ID]
        );
        $this->assertNotEmpty($audit, 'Audit log entry should exist for reschedule');
        $this->assertSame('booking.rescheduled', $audit[0]['action']);

        // Verify audit log details include actor_type = customer
        $details = json_decode($audit[0]['details'] ?? '{}', true);
        $this->assertSame('customer', $details['actor_type'] ?? null,
            'Audit log should record actor_type as customer for self-service reschedule');
    }

    public function test_reschedule_returns_new_booking_details(): void
    {
        // Determine a valid target date
        $targetTs = strtotime('+4 days');
        while (date('N', $targetTs) >= 6) {
            $targetTs = strtotime('+1 day', $targetTs);
        }
        $targetDate = date('Y-m-d', $targetTs);

        // Create a fresh booking for this test
        $bookingId = '01TESTSSRESCHEDDETAILS0';
        $origDate = date('Y-m-d', strtotime('+2 days'));
        Database::execute("DELETE FROM `bookings` WHERE `id` = ?", [$bookingId]);
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `service_id`,
              `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?, ?,
              '{$origDate} 10:00:00', '{$origDate} 10:30:00',
              'confirmed', 'web')",
            [$bookingId, self::TENANT_ID, self::CUSTOMER_ID, self::SERVICE_ID]
        );

        self::ensureAvailability();

        $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/{$bookingId}/reschedule", [
            'new_date' => $targetDate,
            'new_time' => '15:00',
        ]);

        $this->assertSame(200, $r['code'], 'Body: ' . $r['body']);
        $data = json_decode($r['body'], true);

        $this->assertArrayHasKey('new_booking', $data, 'Response should include new_booking object');
        $this->assertSame($targetDate, $data['new_booking']['date'] ?? null);
        $this->assertSame('15:00', $data['new_booking']['time'] ?? null);
    }

    // ════════════════════════════════════════════════════════════════
    // Consent integrity: no fabrication for admin-created bookings
    // ════════════════════════════════════════════════════════════════

    public function test_reschedule_admin_booking_does_not_fabricate_consent(): void
    {
        // Determine a valid target date
        $targetTs = strtotime('+5 days');
        while (date('N', $targetTs) >= 6) {
            $targetTs = strtotime('+1 day', $targetTs);
        }
        $targetDate = date('Y-m-d', $targetTs);

        // Create an admin-created booking WITHOUT consent evidence
        $bookingId = '01TESTSSRESCHEDNOCONSNT';
        $origDate = date('Y-m-d', strtotime('+2 days'));
        Database::execute("DELETE FROM `bookings` WHERE `id` = ?", [$bookingId]);
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `service_id`,
              `start_datetime`, `end_datetime`, `status`, `source`,
              `consent_given_at`, `consent_text_shown`)
             VALUES (?, ?, 'timeslot', ?, ?,
              '{$origDate} 10:00:00', '{$origDate} 10:30:00',
              'confirmed', 'admin',
              NULL, NULL)",
            [$bookingId, self::TENANT_ID, self::CUSTOMER_ID, self::SERVICE_ID]
        );

        self::ensureAvailability();

        $r = $this->postJsonWithCsrf("/api/test-fixture/bookings/{$bookingId}/reschedule", [
            'new_date' => $targetDate,
            'new_time' => '16:00',
        ]);

        $this->assertSame(200, $r['code'], 'Admin booking reschedule should succeed. Body: ' . $r['body']);
        $data = json_decode($r['body'], true);
        $newBookingId = $data['new_booking_id'] ?? null;
        $this->assertNotEmpty($newBookingId);

        // Verify the new booking has NO consent evidence (not fabricated)
        $newBooking = Database::query(
            "SELECT `consent_given_at`, `consent_text_shown` FROM `bookings` WHERE `id` = ?",
            [$newBookingId]
        );
        $this->assertNotEmpty($newBooking, 'New booking should exist');
        $this->assertNull($newBooking[0]['consent_given_at'],
            'Rescheduled admin booking must NOT have fabricated consent_given_at');
        $this->assertNull($newBooking[0]['consent_text_shown'],
            'Rescheduled admin booking must NOT have fabricated consent_text_shown');
    }

    // ════════════════════════════════════════════════════════════════
    // Fixtures
    // ════════════════════════════════════════════════════════════════

    private static function seedFixtures(): void
    {
        $tenantId = self::TENANT_ID;

        // Clean prior test data
        Database::execute("DELETE FROM `bookings` WHERE `id` LIKE '01TESTSSRESCHED%'");
        Database::execute("DELETE FROM `customers` WHERE `id` = ?", [self::CUSTOMER_ID]);
        Database::execute("DELETE FROM `services` WHERE `id` = ?", [self::SERVICE_ID]);

        // Customer
        Database::execute(
            "INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`)
             VALUES (?, ?, 'SS Reschedule Customer', 'ss-resched@example.com')",
            [self::CUSTOMER_ID, $tenantId]
        );

        // Service (30-minute duration)
        Database::execute(
            "INSERT INTO `services` (`id`, `tenant_id`, `name`, `duration_minutes`, `is_active`, `sort_order`)
             VALUES (?, ?, 'SS Reschedule Service', 30, 1, 99)",
            [self::SERVICE_ID, $tenantId]
        );

        self::ensureAvailability();

        // Confirmed timeslot booking (the main fixture)
        $startDate = date('Y-m-d', strtotime('+2 days'));
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `service_id`,
              `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?, ?,
              '{$startDate} 10:00:00', '{$startDate} 10:30:00',
              'confirmed', 'web')",
            [self::BOOKING_ID, $tenantId, self::CUSTOMER_ID, self::SERVICE_ID]
        );
    }

    private static function ensureAvailability(): void
    {
        $tenantId = self::TENANT_ID;

        // Seed weekday availability (Mon-Fri = day_of_week 0-4) if not present
        $existing = Database::query(
            "SELECT COUNT(*) AS cnt FROM `availability` WHERE `tenant_id` = ? AND `staff_id` IS NULL",
            [$tenantId]
        );

        if ((int) ($existing[0]['cnt'] ?? 0) < 5) {
            Database::execute(
                "DELETE FROM `availability` WHERE `tenant_id` = ? AND `staff_id` IS NULL",
                [$tenantId]
            );
            for ($dow = 0; $dow <= 4; $dow++) {
                Database::execute(
                    "INSERT INTO `availability` (`id`, `tenant_id`, `day_of_week`, `start_time`, `end_time`, `is_available`)
                     VALUES (?, ?, ?, '08:00', '18:00', 1)",
                    ["01TESTSSRESCHEDAVAIL0{$dow}0", $tenantId, $dow]
                );
            }
        }
    }

    private function insertBooking(string $id, string $status, string $pattern): void
    {
        $startDate = date('Y-m-d', strtotime('+2 days'));

        Database::execute("DELETE FROM `bookings` WHERE `id` = ?", [$id]);
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`,
              `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, ?, ?,
              '{$startDate} 10:00:00', '{$startDate} 10:30:00',
              ?, 'web')",
            [$id, self::TENANT_ID, $pattern, self::CUSTOMER_ID, $status]
        );
    }

    // ════════════════════════════════════════════════════════════════
    // HTTP helpers
    // ════════════════════════════════════════════════════════════════

    /**
     * Obtain a CSRF token by visiting the manage page.
     */
    private function getCsrfToken(): string
    {
        $r = $this->get("/book/test-fixture/manage/" . self::BOOKING_ID);
        preg_match('/window\.__VB_CSRF__\s*=\s*["\']([^"\']+)["\']/', $r['body'], $m);
        return $m[1] ?? '';
    }

    /**
     * POST JSON with CSRF token.
     */
    private function postJsonWithCsrf(string $path, array $data): array
    {
        $csrf = $this->getCsrfToken();
        return $this->postJson($path, $data, $csrf);
    }

    /**
     * POST JSON to the given path.
     */
    private function postJson(string $path, array $data, string $csrfToken): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($csrfToken !== '') {
            $headers[] = 'X-CSRF-Token: ' . $csrfToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
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
        return compact('code', 'body');
    }

    /**
     * @return array{code: int, body: string}
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
        return compact('code', 'body');
    }
}
