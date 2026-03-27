<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\EnvLoader;
use App\Engine\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * HTTP-level integration tests for the public booking flow.
 *
 * Covers:
 * - GET /book/{slug}           → booking page shell loads
 * - GET /book/{slug}           → 404 for missing/inactive tenant
 * - GET /api/{slug}/services   → returns active services
 * - GET /api/{slug}/staff      → returns filtered staff
 * - GET /api/{slug}/availability → returns slots for a date
 * - GET /api/{slug}/available-dates → returns available dates
 * - POST /api/{slug}/bookings  → creates booking with consent evidence
 * - POST /api/{slug}/bookings  → rejects duplicate booking (409)
 * - POST /api/{slug}/bookings  → validates required fields
 *
 * Deterministic: reachability is checked once in setUpBeforeClass,
 * not per-test. Rate limit records for the test IP are cleared
 * before the class runs.
 */
final class BookingFlowTest extends TestCase
{
    private string $baseUrl;
    private array $cleanupIds = [];

    private static bool $appReachable = false;
    private static bool $dbReady = false;

    /** @var array{slug: string, tenant_id: string, customer_email: string, service_id: string, staff_id: string} */
    private static array $seed = [];

    /**
     * One-time reachability + DB + seed. No per-test /health hits.
     */
    public static function setUpBeforeClass(): void
    {
        $baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');

        // Single reachability check
        $ch = curl_init($baseUrl . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Accept 200 or 429 — the app is running either way
        if ($code === 0) {
            return; // App unreachable, tests will skip per setUp
        }

        self::$appReachable = true;

        // DB connection
        try {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            EnvLoader::load(dirname(__DIR__, 2) . '/.env');
            Database::connect();
            self::$dbReady = true;
        } catch (\Throwable) {
            return;
        }

        // Clear rate limit records for 127.0.0.1 so the test suite starts fresh
        try {
            Database::execute(
                "DELETE FROM `rate_limits` WHERE `ip` = '127.0.0.1'",
            );
        } catch (\Throwable) {
            // table may not exist
        }

        // Seed test data
        self::$seed = self::seedTestTenant();
    }

    protected function setUp(): void
    {
        $this->baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');

        if (!self::$appReachable) {
            $this->markTestSkipped('App not reachable at ' . $this->baseUrl);
        }
        if (!self::$dbReady) {
            $this->markTestSkipped('Database not available');
        }
        if (empty(self::$seed)) {
            $this->markTestSkipped('Test seed not available');
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanupIds) as [$table, $id]) {
            try {
                Database::execute("DELETE FROM `{$table}` WHERE `id` = ?", [$id]);
            } catch (\Throwable) {
                // Best-effort
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (empty(self::$seed)) {
            return;
        }

        try {
            Database::execute('DELETE FROM `service_staff` WHERE `service_id` = ?', [self::$seed['service_id']]);
            Database::execute('DELETE FROM `availability` WHERE `tenant_id` = ?', [self::$seed['tenant_id']]);
            Database::execute('DELETE FROM `bookings` WHERE `tenant_id` = ?', [self::$seed['tenant_id']]);
            Database::execute('DELETE FROM `customers` WHERE `tenant_id` = ?', [self::$seed['tenant_id']]);
            Database::execute('DELETE FROM `staff` WHERE `tenant_id` = ?', [self::$seed['tenant_id']]);
            Database::execute('DELETE FROM `services` WHERE `tenant_id` = ?', [self::$seed['tenant_id']]);
            Database::execute('DELETE FROM `tenants` WHERE `id` = ?', [self::$seed['tenant_id']]);
        } catch (\Throwable) {
            // Best-effort
        }

        self::$seed = [];
    }

    // ════════════════════════════════════════════════════════════════
    // Page load tests
    // ════════════════════════════════════════════════════════════════

    public function testBookingPageLoads(): void
    {
        $res = $this->httpGet('/book/' . self::$seed['slug']);

        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Booking Flow Test', $res['body'], 'Page must contain tenant name');
        $this->assertStringContainsString('__VB_CONFIG__', $res['body'], 'Page must inject config blob');
        $this->assertStringContainsString('booking.js', $res['body'], 'Page must load booking.js');
        $this->assertStringContainsString('booking-css.css', $res['body'], 'Page must load booking CSS');
    }

    public function testBookingPageConfigContainsBrandColor(): void
    {
        $res = $this->httpGet('/book/' . self::$seed['slug']);

        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('"brand_color":"#3B82F6"', $res['body']);
    }

    public function testBookingPage404ForMissingSlugs(): void
    {
        $res = $this->httpGet('/book/nonexistent-test-tenant-xyz');

        $this->assertSame(404, $res['code']);
        $this->assertStringContainsString('booking-css.css', $res['body'], '404 must use booking design system');
        $this->assertStringContainsString('not found', strtolower($res['body']));
    }

    // ════════════════════════════════════════════════════════════════
    // Services API
    // ════════════════════════════════════════════════════════════════

    public function testServicesEndpointReturnsActiveServices(): void
    {
        $res = $this->httpGetJson('/api/' . self::$seed['slug'] . '/services');

        $this->assertSame(200, $res['code'], 'Services endpoint must return 200. Body: ' . $res['body']);
        $data = json_decode($res['body'], true);

        $this->assertArrayHasKey('services', $data);
        $this->assertNotEmpty($data['services'], 'Should return at least one service');

        $service = $data['services'][0];
        $this->assertArrayHasKey('id', $service);
        $this->assertArrayHasKey('name', $service);
        $this->assertArrayHasKey('duration_minutes', $service);
        $this->assertArrayHasKey('price', $service);
    }

    public function testServicesEndpoint404ForBadSlug(): void
    {
        $res = $this->httpGetJson('/api/no-such-tenant/services');

        $this->assertSame(404, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('tenant_not_found', $data['error'] ?? '');
    }

    // ════════════════════════════════════════════════════════════════
    // Staff API
    // ════════════════════════════════════════════════════════════════

    public function testStaffEndpointReturnsAllStaff(): void
    {
        $res = $this->httpGetJson('/api/' . self::$seed['slug'] . '/staff');

        $this->assertSame(200, $res['code'], 'Staff endpoint must return 200. Body: ' . $res['body']);
        $data = json_decode($res['body'], true);

        $this->assertArrayHasKey('staff', $data);
        $this->assertNotEmpty($data['staff']);

        $member = $data['staff'][0];
        $this->assertArrayHasKey('id', $member);
        $this->assertArrayHasKey('name', $member);
    }

    public function testStaffFilteredByService(): void
    {
        $res = $this->httpGetJson('/api/' . self::$seed['slug'] . '/staff?service_id=' . self::$seed['service_id']);

        $this->assertSame(200, $res['code'], 'Filtered staff must return 200. Body: ' . $res['body']);
        $data = json_decode($res['body'], true);

        $this->assertNotEmpty($data['staff'] ?? [], 'Service-staff pivot should return linked staff');
    }

    // ════════════════════════════════════════════════════════════════
    // Availability API
    // ════════════════════════════════════════════════════════════════

    public function testAvailabilityEndpointReturnsSlotsForWeekday(): void
    {
        $nextMon = new \DateTimeImmutable('next Monday');
        $date = $nextMon->format('Y-m-d');

        $res = $this->httpGetJson('/api/' . self::$seed['slug'] . '/availability?date=' . $date);

        $this->assertSame(200, $res['code'], 'Availability must return 200. Body: ' . $res['body']);
        $data = json_decode($res['body'], true);

        $this->assertArrayHasKey('slots', $data);
        $this->assertNotEmpty($data['slots'], 'Monday should have available slots');

        foreach ($data['slots'] as $slot) {
            $this->assertArrayHasKey('time', $slot);
            $this->assertArrayHasKey('end_time', $slot);
        }
    }

    public function testAvailabilityRejectsInvalidDate(): void
    {
        $res = $this->httpGetJson('/api/' . self::$seed['slug'] . '/availability?date=invalid');

        $this->assertSame(400, $res['code']);
    }

    public function testAvailableDatesEndpointReturnsDatesArray(): void
    {
        $now = new \DateTimeImmutable();
        $year = $now->format('Y');
        $month = $now->format('n');

        $res = $this->httpGetJson('/api/' . self::$seed['slug'] . "/available-dates?year={$year}&month={$month}");

        $this->assertSame(200, $res['code'], 'Available dates must return 200. Body: ' . $res['body']);
        $data = json_decode($res['body'], true);

        $this->assertArrayHasKey('dates', $data);
        $this->assertIsArray($data['dates']);
    }

    // ════════════════════════════════════════════════════════════════
    // Booking creation — happy path
    // ════════════════════════════════════════════════════════════════

    public function testCreateBookingSucceeds(): void
    {
        $slot = $this->getFirstAvailableSlot('next Monday');

        $payload = [
            'service_id'     => self::$seed['service_id'],
            'staff_id'       => self::$seed['staff_id'],
            'start_datetime' => $slot['date'] . 'T' . $slot['time'] . ':00',
            'customer'       => [
                'name'  => 'Integration Tester',
                'email' => 'booking-test-' . substr(Ulid::generate(), -6) . '@example.com',
            ],
            'consent_given' => true,
            'notes'         => 'Integration test booking',
            '__ts'          => (time() - 10) * 1000,
        ];

        $res = $this->httpPostJson('/api/' . self::$seed['slug'] . '/bookings', $payload);

        $this->assertSame(201, $res['code'], 'Booking creation must return 201. Body: ' . $res['body']);
        $data = json_decode($res['body'], true);

        $this->assertArrayHasKey('booking', $data);
        $booking = $data['booking'];
        $this->assertArrayHasKey('id', $booking);
        $this->assertSame($slot['date'], $booking['date']);
        $this->assertSame($slot['time'], $booking['time']);
        $this->assertTrue($booking['consent_recorded'], 'Consent must be recorded');

        $this->cleanupIds[] = ['bookings', $booking['id']];
    }

    // ════════════════════════════════════════════════════════════════
    // Consent evidence via public flow
    // ════════════════════════════════════════════════════════════════

    public function testConsentEvidenceRecordedViaPublicBooking(): void
    {
        $slot = $this->getFirstAvailableSlot('next Tuesday');

        $payload = [
            'service_id'     => self::$seed['service_id'],
            'start_datetime' => $slot['date'] . 'T' . $slot['time'] . ':00',
            'customer'       => [
                'name'  => 'Consent Evidence Tester',
                'email' => 'consent-test-' . substr(Ulid::generate(), -6) . '@example.com',
            ],
            'consent_given' => true,
            '__ts'          => (time() - 10) * 1000,
        ];

        $res = $this->httpPostJson('/api/' . self::$seed['slug'] . '/bookings', $payload);
        $this->assertSame(201, $res['code'], 'Consent booking must return 201. Body: ' . $res['body']);

        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('booking', $data);
        $bookingId = $data['booking']['id'];
        $this->cleanupIds[] = ['bookings', $bookingId];

        // Verify consent was recorded in the database
        $rows = Database::query(
            'SELECT `consent_given_at`, `consent_text_shown` FROM `bookings` WHERE `id` = ?',
            [$bookingId]
        );

        $this->assertCount(1, $rows);
        $this->assertNotNull($rows[0]['consent_given_at'], 'consent_given_at must be set');
        $this->assertNotNull($rows[0]['consent_text_shown'], 'consent_text_shown must be recorded');
        $this->assertStringContainsString(
            'I agree to the processing',
            $rows[0]['consent_text_shown'],
            'Must record the exact consent text shown'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Slot conflict (double-booking prevention)
    // ════════════════════════════════════════════════════════════════

    public function testDoubleBookingReturns409(): void
    {
        $slot = $this->getFirstAvailableSlot('next Wednesday', self::$seed['staff_id']);

        $basePayload = [
            'service_id'     => self::$seed['service_id'],
            'staff_id'       => self::$seed['staff_id'],
            'start_datetime' => $slot['date'] . 'T' . $slot['time'] . ':00',
            'consent_given'  => true,
            '__ts'           => (time() - 10) * 1000,
        ];

        // First booking — should succeed
        $payload1 = $basePayload;
        $payload1['customer'] = [
            'name'  => 'First Booker',
            'email' => 'first-' . substr(Ulid::generate(), -6) . '@example.com',
        ];

        $res1 = $this->httpPostJson('/api/' . self::$seed['slug'] . '/bookings', $payload1);
        $this->assertSame(201, $res1['code'], 'First booking must succeed. Body: ' . $res1['body']);

        $data1 = json_decode($res1['body'], true);
        $this->assertArrayHasKey('booking', $data1);
        $this->cleanupIds[] = ['bookings', $data1['booking']['id']];

        // Second booking — same slot, same staff → should be 409
        $payload2 = $basePayload;
        $payload2['customer'] = [
            'name'  => 'Second Booker',
            'email' => 'second-' . substr(Ulid::generate(), -6) . '@example.com',
        ];

        $res2 = $this->httpPostJson('/api/' . self::$seed['slug'] . '/bookings', $payload2);
        $this->assertSame(409, $res2['code'], 'Double booking must be rejected with 409. Body: ' . $res2['body']);

        $data2 = json_decode($res2['body'], true);
        $this->assertSame('slot_unavailable', $data2['error'] ?? '');
        $this->assertArrayHasKey('alternatives', $data2, 'Must offer alternative slots');
    }

    // ════════════════════════════════════════════════════════════════
    // Validation
    // ════════════════════════════════════════════════════════════════

    public function testBookingRejectsMissingCustomer(): void
    {
        $payload = [
            'service_id'     => self::$seed['service_id'],
            'start_datetime' => '2026-07-01T10:00:00',
            'customer'       => ['name' => '', 'email' => ''],
            'consent_given'  => true,
            '__ts'           => (time() - 10) * 1000,
        ];

        $res = $this->httpPostJson('/api/' . self::$seed['slug'] . '/bookings', $payload);
        $this->assertSame(422, $res['code'], 'Missing customer must return 422. Body: ' . $res['body']);

        $data = json_decode($res['body'], true);
        $this->assertSame('validation', $data['error'] ?? '');
    }

    public function testBookingRejectsInvalidEmail(): void
    {
        $payload = [
            'service_id'     => self::$seed['service_id'],
            'start_datetime' => '2026-07-01T10:00:00',
            'customer'       => ['name' => 'Test', 'email' => 'not-an-email'],
            'consent_given'  => true,
            '__ts'           => (time() - 10) * 1000,
        ];

        $res = $this->httpPostJson('/api/' . self::$seed['slug'] . '/bookings', $payload);
        $this->assertSame(422, $res['code'], 'Invalid email must return 422. Body: ' . $res['body']);
    }

    public function testBookingRejectsSpamSubmission(): void
    {
        $payload = [
            'service_id'     => self::$seed['service_id'],
            'start_datetime' => '2026-07-01T10:00:00',
            'customer'       => ['name' => 'Bot', 'email' => 'bot@example.com'],
            'consent_given'  => true,
            '__ts'           => time() * 1000, // Current time — too fast
        ];

        $res = $this->httpPostJson('/api/' . self::$seed['slug'] . '/bookings', $payload);
        $this->assertSame(422, $res['code'], 'Spam must return 422. Body: ' . $res['body']);

        $data = json_decode($res['body'], true);
        $this->assertSame('spam_detected', $data['error'] ?? '');
    }

    // ════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════

    /**
     * Fetch availability for the given relative day and return the first slot.
     * Fails the calling test with a clear message if no slots are available
     * (never reads response body without first asserting the status code).
     *
     * @return array{date: string, time: string, end_time: string}
     */
    private function getFirstAvailableSlot(string $relativeDay, ?string $staffId = null): array
    {
        $date = (new \DateTimeImmutable($relativeDay))->format('Y-m-d');
        $params = '?date=' . $date;
        if ($staffId !== null) {
            $params .= '&staff_id=' . $staffId;
        }

        $res = $this->httpGetJson('/api/' . self::$seed['slug'] . '/availability' . $params);

        $this->assertSame(
            200,
            $res['code'],
            "Availability for {$relativeDay} ({$date}) must return 200. Got {$res['code']}. Body: {$res['body']}"
        );

        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('slots', $data, 'Response must contain slots key');
        $this->assertNotEmpty($data['slots'], "{$relativeDay} ({$date}) must have available slots");

        $slot = $data['slots'][0];
        $slot['date'] = $date;

        return $slot;
    }

    // ════════════════════════════════════════════════════════════════
    // HTTP helpers
    // ════════════════════════════════════════════════════════════════

    private function httpGet(string $path): array
    {
        return $this->request('GET', $path);
    }

    private function httpGetJson(string $path): array
    {
        return $this->request('GET', $path, [], ['Accept: application/json']);
    }

    private function httpPostJson(string $path, array $payload): array
    {
        return $this->request('POST', $path, $payload, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    /**
     * @return array{code: int, headers: string, body: string}
     */
    private function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (in_array('Content-Type: application/json', $headers, true)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [
            'code'    => $code,
            'headers' => substr($response, 0, $headerSize),
            'body'    => substr($response, $headerSize),
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // Seed data
    // ════════════════════════════════════════════════════════════════

    /**
     * Seeds a complete test tenant with service, staff, availability,
     * and service-staff pivot. Returns identifiers for test use.
     */
    private static function seedTestTenant(): array
    {
        $tenantId = Ulid::generate();
        $serviceId = Ulid::generate();
        $staffId = Ulid::generate();
        $slug = 'test-booking-' . substr($tenantId, -8);

        // Tenant
        Database::execute(
            "INSERT INTO `tenants` (`id`, `slug`, `name`, `email`, `booking_pattern`, `status`,
             `brand_color`, `timezone`, `locale`, `currency`, `requires_consent`, `consent_text`,
             `slot_duration_minutes`, `max_advance_days`)
             VALUES (?, ?, 'Booking Flow Test', 'test@booking.test', 'timeslot', 'active',
             '#3B82F6', 'Europe/Amsterdam', 'en', 'EUR', 1,
             'I agree to the processing of my personal data for booking purposes.', 30, 60)",
            [$tenantId, $slug]
        );

        // Service: 30-minute appointment
        Database::execute(
            "INSERT INTO `services` (`id`, `tenant_id`, `name`, `description`, `duration_minutes`, `price`, `is_active`, `sort_order`)
             VALUES (?, ?, 'Test Consultation', 'A test service', 30, 25.00, 1, 1)",
            [$serviceId, $tenantId]
        );

        // Staff member
        Database::execute(
            "INSERT INTO `staff` (`id`, `tenant_id`, `name`, `email`, `is_active`, `sort_order`)
             VALUES (?, ?, 'Test Practitioner', 'staff@booking.test', 1, 1)",
            [$staffId, $tenantId]
        );

        // Service-staff pivot
        Database::execute(
            'INSERT INTO `service_staff` (`service_id`, `staff_id`) VALUES (?, ?)',
            [$serviceId, $staffId]
        );

        // Availability: Mon–Fri 09:00–17:00 (day_of_week: 0=Mon, 4=Fri per ISO convention)
        for ($day = 0; $day <= 4; $day++) {
            Database::execute(
                "INSERT INTO `availability` (`id`, `tenant_id`, `day_of_week`, `start_time`, `end_time`)
                 VALUES (?, ?, ?, '09:00', '17:00')",
                [Ulid::generate(), $tenantId, $day]
            );
        }

        return [
            'slug'           => $slug,
            'tenant_id'      => $tenantId,
            'customer_email' => 'test@booking.test',
            'service_id'     => $serviceId,
            'staff_id'       => $staffId,
        ];
    }
}
