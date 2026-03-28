<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\EnvLoader;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the admin booking/tenant/dashboard routes.
 *
 * Tests HTTP-level behavior via curl against the running application.
 * Requires the app to be reachable at APP_TEST_URL.
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
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            EnvLoader::load(dirname(__DIR__, 2) . '/.env');
            Database::connect();
            Database::execute('TRUNCATE TABLE `rate_limits`');
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
        if (file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Operator: /admin/bookings
    // ════════════════════════════════════════════════════════════════

    public function test_operator_bookings_list_returns_200(): void
    {
        $this->doLogin();
        $r = $this->get('/admin/bookings');
        $this->assertSame(200, $r['code'], 'Bookings list should be accessible');
        $this->assertStringContainsString('Bookings', $r['body']);
    }

    // ════════════════════════════════════════════════════════════════
    // Operator: /admin/tenants
    // ════════════════════════════════════════════════════════════════

    public function test_operator_tenants_list_returns_200(): void
    {
        $this->doLogin();
        $r = $this->get('/admin/tenants');
        $this->assertSame(200, $r['code'], 'Tenants list should be accessible');
        $this->assertStringContainsString('Tenants', $r['body']);
    }

    public function test_operator_tenant_create_returns_200(): void
    {
        $this->doLogin();
        $r = $this->get('/admin/tenants/create');
        $this->assertSame(200, $r['code'], 'Create tenant form should be accessible');
    }

    // ════════════════════════════════════════════════════════════════
    // Operator: /admin/tenants/{tenant_id} (tenant dashboard)
    // ════════════════════════════════════════════════════════════════

    public function test_operator_tenant_dashboard_returns_200_for_valid_tenant(): void
    {
        $this->doLogin();

        // Find a tenant from the database
        $tenants = Database::query("SELECT `id` FROM `tenants` LIMIT 1");
        if (empty($tenants)) {
            $this->markTestSkipped('No tenants in database');
        }

        $tenantId = $tenants[0]['id'];
        $r = $this->get("/admin/tenants/{$tenantId}");
        $this->assertSame(200, $r['code'], 'Tenant dashboard should be accessible');
    }

    public function test_operator_tenant_dashboard_redirects_for_invalid_tenant(): void
    {
        $this->doLogin();
        $r = $this->get('/admin/tenants/01NONEXISTENT00000000000');
        $this->assertSame(302, $r['code'], 'Invalid tenant should redirect');
    }

    // ════════════════════════════════════════════════════════════════
    // Operator: /admin/tenants/{tenant_id}/bookings (tenant-context)
    // ════════════════════════════════════════════════════════════════

    public function test_operator_tenant_bookings_returns_200(): void
    {
        $this->doLogin();

        $tenants = Database::query("SELECT `id` FROM `tenants` LIMIT 1");
        if (empty($tenants)) {
            $this->markTestSkipped('No tenants in database');
        }

        $tenantId = $tenants[0]['id'];
        $r = $this->get("/admin/tenants/{$tenantId}/bookings");
        $this->assertSame(200, $r['code'], 'Tenant bookings list should be accessible');
    }

    // ════════════════════════════════════════════════════════════════
    // Unauthenticated access redirects to login
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
    // Dashboard correctness
    // ════════════════════════════════════════════════════════════════

    public function test_operator_dashboard_shows_real_metrics(): void
    {
        $this->doLogin();
        $r = $this->get('/admin');
        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString('Active Tenants', $r['body']);
        $this->assertStringContainsString('Bookings Today', $r['body']);
    }

    // ── Helpers ──

    private function doLogin(): void
    {
        $response = $this->get('/admin/login');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $response['body'], $m);
        $csrf = $m[1] ?? '';

        $this->post('/admin/login', [
            'email' => 'operator@example.com',
            'password' => 'welcome3210',
            '_csrf_token' => $csrf,
        ]);
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
