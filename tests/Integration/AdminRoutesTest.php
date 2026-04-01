<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for admin routes.
 *
 * Covers operator flows, business-user flows, unauthenticated redirects,
 * and dashboard correctness. All fixtures are provisioned automatically
 * by TestFixtures::provision().
 */
final class AdminRoutesTest extends TestCase
{
    private static bool $appReachable = false;
    private string $baseUrl;
    private string $cookieJar;

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
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'vb_admin_test_') ?: '/tmp/vb_admin_test_cookies';
    }

    protected function tearDown(): void
    {
        if (isset($this->cookieJar) && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Operator: page access
    // ════════════════════════════════════════════════════════════════

    public function test_operator_bookings_list_returns_200(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/bookings');
        $this->assertSame(200, $r['code'], 'Bookings list should be accessible');
        $this->assertStringContainsString('Bookings', $r['body']);
    }

    public function test_operator_tenants_list_returns_200(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/tenants');
        $this->assertSame(200, $r['code'], 'Tenants list should be accessible');
        $this->assertStringContainsString('Tenants', $r['body']);
    }

    public function test_operator_tenant_create_returns_200(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/tenants/create');
        $this->assertSame(200, $r['code'], 'Create tenant form should be accessible');
    }

    public function test_operator_tenant_dashboard_returns_200(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/tenants/' . TestFixtures::BUSINESS_TENANT_ID);
        $this->assertSame(200, $r['code'], 'Tenant dashboard should be accessible');
    }

    public function test_operator_tenant_dashboard_redirects_for_invalid_tenant(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/tenants/01NONEXISTENT00000000000');
        $this->assertSame(302, $r['code'], 'Invalid tenant should redirect');
    }

    public function test_operator_tenant_bookings_returns_200(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/tenants/' . TestFixtures::BUSINESS_TENANT_ID . '/bookings');
        $this->assertSame(200, $r['code'], 'Tenant bookings list should be accessible');
    }

    public function test_operator_settings_returns_200(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/settings');
        $this->assertSame(200, $r['code'], 'Settings should be accessible for operators');
    }

    // ════════════════════════════════════════════════════════════════
    // Document title contract: browser tab titles must be page-specific
    // ════════════════════════════════════════════════════════════════

    public function test_tenant_create_document_title_is_page_specific(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/tenants/create');
        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            '<title>Create Tenant',
            $r['body'],
            'Browser tab should say "Create Tenant", not just "Tenants"'
        );
    }

    public function test_tenant_edit_document_title_is_page_specific(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/tenants/' . TestFixtures::BUSINESS_TENANT_ID . '/edit');
        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            '<title>Edit Tenant',
            $r['body'],
            'Browser tab should say "Edit Tenant", not just "Tenants"'
        );
    }

    public function test_booking_detail_document_title_is_page_specific(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/bookings/' . TestFixtures::BOOKING_ID);
        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            '<title>Booking Details',
            $r['body'],
            'Browser tab should say "Booking Details", not just "Bookings"'
        );
    }

    public function test_tenant_list_document_title_is_section_name(): void
    {
        $this->doLoginOperator();
        $r = $this->get('/admin/tenants');
        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            '<title>Tenants',
            $r['body'],
            'Tenant list tab should say "Tenants"'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Business user: redirect, allowed access, and denied access
    // ════════════════════════════════════════════════════════════════

    public function test_business_user_admin_redirects_to_tenant_dashboard(): void
    {
        $this->doLoginBusinessUser();
        $r = $this->get('/admin');
        $this->assertSame(302, $r['code'], 'Business user on /admin should be redirected');
        $this->assertStringContainsString(
            '/admin/tenants/' . TestFixtures::BUSINESS_TENANT_ID,
            $r['location'],
            'Redirect should point to the business user\'s tenant dashboard'
        );
    }

    public function test_business_user_tenant_dashboard_returns_200(): void
    {
        $this->doLoginBusinessUser();
        $r = $this->get('/admin/tenants/' . TestFixtures::BUSINESS_TENANT_ID);
        $this->assertSame(200, $r['code'], 'Business user should access own tenant dashboard');
    }

    public function test_business_user_tenant_bookings_returns_200(): void
    {
        $this->doLoginBusinessUser();
        $r = $this->get('/admin/tenants/' . TestFixtures::BUSINESS_TENANT_ID . '/bookings');
        $this->assertSame(200, $r['code'], 'Business user should access own tenant bookings');
    }

    public function test_business_user_settings_returns_403(): void
    {
        $this->doLoginBusinessUser();
        $r = $this->get('/admin/settings');
        $this->assertSame(403, $r['code'], 'Business user should be denied operator-only settings');
    }

    public function test_business_user_settings_account_returns_403(): void
    {
        $this->doLoginBusinessUser();
        $r = $this->get('/admin/settings/account');
        $this->assertSame(403, $r['code'], 'Business user should be denied operator-only account page');
    }

    public function test_business_user_other_tenant_returns_403(): void
    {
        $this->doLoginBusinessUser();
        // Access a tenant that is NOT theirs
        $r = $this->get('/admin/tenants/01SOMEOTHERTENANT0000000');
        $this->assertSame(403, $r['code'], 'Business user should be denied access to other tenants');
    }

    // ════════════════════════════════════════════════════════════════
    // Unauthenticated access redirects
    // ════════════════════════════════════════════════════════════════

    public function test_unauthenticated_bookings_redirects_to_login(): void
    {
        $r = $this->get('/admin/bookings');
        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString('/admin/login', $r['location']);
    }

    public function test_unauthenticated_tenants_redirects_to_login(): void
    {
        $r = $this->get('/admin/tenants');
        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString('/admin/login', $r['location']);
    }

    // ════════════════════════════════════════════════════════════════
    // Dashboard correctness: bounded counts and nearest-first
    // ════════════════════════════════════════════════════════════════

    public function test_operator_dashboard_shows_bounded_metrics(): void
    {
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;
        $customerId = $this->ensureTestCustomer($tenantId);

        $todayStart = date('Y-m-d 10:00:00');
        $todayEnd = date('Y-m-d 10:30:00');
        $oldStart = date('Y-m-d 10:00:00', strtotime('-30 days'));
        $oldEnd = date('Y-m-d 10:30:00', strtotime('-30 days'));

        // Clean all bookings except the test fixture booking
        Database::execute("DELETE FROM `bookings` WHERE `id` != ?", [TestFixtures::BOOKING_ID]);

        // Insert today's booking (should appear in both Today and This Week)
        Database::execute(
            "INSERT INTO `bookings` (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?, ?, ?, 'confirmed', 'web')",
            ['01TESTBKTODAY00000000000', $tenantId, $customerId, $todayStart, $todayEnd]
        );

        // Insert old booking (30 days ago — outside the 7-day week window)
        Database::execute(
            "INSERT INTO `bookings` (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?, ?, ?, 'confirmed', 'web')",
            ['01TESTBKOLD0000000000000', $tenantId, $customerId, $oldStart, $oldEnd]
        );

        $this->doLoginOperator();
        $r = $this->get('/admin');
        $this->assertSame(200, $r['code']);

        // Extract "Bookings Today" metric value
        $todayMatch = preg_match('/Bookings Today.*?vb-metric-value[^>]*>(\d+)/s', $r['body'], $mToday);
        $this->assertSame(1, $todayMatch, 'Should find Bookings Today metric');
        $todayCount = (int) ($mToday[1] ?? 0);
        $this->assertGreaterThanOrEqual(1, $todayCount, 'Bookings Today should be >= 1');

        // Extract "This Week" metric value
        $weekMatch = preg_match('/This Week.*?vb-metric-value[^>]*>(\d+)/s', $r['body'], $mWeek);
        $this->assertSame(1, $weekMatch, 'Should find This Week metric');
        $weekCount = (int) ($mWeek[1] ?? 0);

        // The 30-day-old booking must be excluded from This Week.
        // If both test bookings were counted, weekCount would be todayCount + 1.
        $this->assertSame(
            $todayCount,
            $weekCount,
            'This Week should equal Bookings Today — the 30-day-old booking must be excluded from the 7-day window'
        );
    }

    public function test_tenant_dashboard_shows_nearest_first_upcoming(): void
    {
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;
        $customerId = $this->ensureTestCustomer($tenantId);

        // Clean prior test bookings
        Database::execute("DELETE FROM `bookings` WHERE `id` LIKE '01TESTBK%'");

        $futureNear = date('Y-m-d 09:00:00', strtotime('+1 day'));
        $futureNearEnd = date('Y-m-d 09:30:00', strtotime('+1 day'));
        $futureFar = date('Y-m-d 14:00:00', strtotime('+5 days'));
        $futureFarEnd = date('Y-m-d 14:30:00', strtotime('+5 days'));

        // Insert far booking first (to confirm sort order, not insertion order)
        Database::execute(
            "INSERT INTO `bookings` (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?, ?, ?, 'confirmed', 'web')",
            ['01TESTBKFAR0000000000000', $tenantId, $customerId, $futureFar, $futureFarEnd]
        );

        // Insert near booking second
        Database::execute(
            "INSERT INTO `bookings` (`id`, `tenant_id`, `booking_pattern`, `customer_id`, `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?, ?, ?, 'confirmed', 'web')",
            ['01TESTBKNEAR000000000000', $tenantId, $customerId, $futureNear, $futureNearEnd]
        );

        $this->doLoginOperator();
        $r = $this->get('/admin/tenants/' . $tenantId);
        $this->assertSame(200, $r['code']);

        // "Next Up" should show the near booking before the far booking
        $nearDate = date('M j', strtotime('+1 day'));
        $farDate = date('M j', strtotime('+5 days'));

        $nearPos = strpos($r['body'], $nearDate);
        $farPos = strpos($r['body'], $farDate);

        $this->assertNotFalse($nearPos, "Near booking date ($nearDate) should appear on tenant dashboard");
        $this->assertNotFalse($farPos, "Far booking date ($farDate) should appear on tenant dashboard");
        $this->assertLessThan($farPos, $nearPos, 'Nearest booking should appear before the far one (ASC sort)');
    }

    // ── Helpers ──

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
     * Ensure a test customer exists for booking seed data.
     * Uses DELETE + INSERT for deterministic state.
     */
    private function ensureTestCustomer(string $tenantId): string
    {
        $id = '01TESTCUSTOMER0000000000';
        Database::execute(
            "DELETE FROM `customers` WHERE `id` = ? OR `email` = 'customer@example.com'",
            [$id]
        );
        Database::execute(
            "INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`) VALUES (?, ?, 'Test Customer', 'customer@example.com')",
            [$id, $tenantId]
        );
        return $id;
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
