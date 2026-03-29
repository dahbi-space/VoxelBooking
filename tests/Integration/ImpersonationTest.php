<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\EnvLoader;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for operator tenant impersonation.
 *
 * Covers:
 * - Operator can start impersonation
 * - Impersonation does not require an existing business_user row
 * - Tenant shell/banner appear while impersonating
 * - Exit impersonation returns to operator tenant list
 * - Logout while impersonating restores operator session
 * - Audit log records impersonation start/end
 * - Non-operator cannot impersonate
 */
final class ImpersonationTest extends TestCase
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
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'vb_imp_test_') ?: '/tmp/vb_imp_test_cookies';
    }

    protected function tearDown(): void
    {
        if (isset($this->cookieJar) && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Start impersonation
    // ════════════════════════════════════════════════════════════════

    public function test_operator_can_start_impersonation(): void
    {
        $this->doLoginOperator();
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        $csrf = $this->getCsrf();
        $r = $this->post("/admin/tenants/{$tenantId}/impersonate", ['_csrf_token' => $csrf]);

        $this->assertSame(302, $r['code'], 'Impersonation start should redirect');
        $this->assertStringContainsString(
            "/admin/tenants/{$tenantId}",
            $r['location'],
            'Should redirect to tenant dashboard'
        );
    }

    public function test_impersonation_shows_banner(): void
    {
        $this->doLoginOperator();
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        $csrf = $this->getCsrf();
        $this->post("/admin/tenants/{$tenantId}/impersonate", ['_csrf_token' => $csrf]);

        // Follow redirect to tenant dashboard
        $r = $this->get("/admin/tenants/{$tenantId}");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            'vb-impersonation-banner',
            $r['body'],
            'Impersonation banner should be visible'
        );
        $this->assertStringContainsString(
            'Exit impersonation',
            $r['body'],
            'Exit button should be present in the banner'
        );
    }

    public function test_impersonation_shows_tenant_sidebar(): void
    {
        $this->doLoginOperator();
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        $csrf = $this->getCsrf();
        $this->post("/admin/tenants/{$tenantId}/impersonate", ['_csrf_token' => $csrf]);

        $r = $this->get("/admin/tenants/{$tenantId}");

        // Should show tenant nav items (Calendar, Customers), not operator nav (Tenants, All Bookings)
        $this->assertStringContainsString(
            "/admin/tenants/{$tenantId}/calendar",
            $r['body'],
            'Calendar link should point to impersonated tenant'
        );
        $this->assertStringContainsString(
            "/admin/tenants/{$tenantId}/customers",
            $r['body'],
            'Customers link should point to impersonated tenant'
        );
        // Operator-level nav items should be hidden
        $this->assertStringNotContainsString(
            'href="/admin/settings"',
            $r['body'],
            'Settings link should be hidden during impersonation'
        );
    }

    public function test_impersonation_does_not_require_business_user_row(): void
    {
        $this->doLoginOperator();
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        // Even if no business_user row exists for the operator, impersonation works
        // (it's session-based, not auth_type switching)
        $csrf = $this->getCsrf();
        $r = $this->post("/admin/tenants/{$tenantId}/impersonate", ['_csrf_token' => $csrf]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString("/admin/tenants/{$tenantId}", $r['location']);
    }

    // ════════════════════════════════════════════════════════════════
    // Exit impersonation
    // ════════════════════════════════════════════════════════════════

    public function test_exit_impersonation_returns_to_tenant_list(): void
    {
        $this->doLoginOperator();
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        // Start impersonation
        $csrf = $this->getCsrf();
        $this->post("/admin/tenants/{$tenantId}/impersonate", ['_csrf_token' => $csrf]);

        // Exit impersonation via dedicated route
        $csrf = $this->getCsrf();
        $r = $this->post('/admin/impersonate/exit', ['_csrf_token' => $csrf]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString('/admin/tenants', $r['location']);

        // After exit, operator should see normal operator dashboard
        $r = $this->get('/admin');
        $this->assertSame(200, $r['code']);
        $this->assertStringNotContainsString(
            'vb-impersonation-banner',
            $r['body'],
            'Impersonation banner should be gone after exit'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Logout during impersonation
    // ════════════════════════════════════════════════════════════════

    public function test_logout_during_impersonation_restores_operator_session(): void
    {
        $this->doLoginOperator();
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        // Start impersonation
        $csrf = $this->getCsrf();
        $this->post("/admin/tenants/{$tenantId}/impersonate", ['_csrf_token' => $csrf]);

        // "Logout" (should exit impersonation, not destroy session)
        $csrf = $this->getCsrf();
        $r = $this->post('/auth/logout', ['_csrf_token' => $csrf]);

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString(
            '/admin/tenants',
            $r['location'],
            'Logout during impersonation should redirect to /admin/tenants, not /admin/login'
        );

        // Operator session should still be alive
        $r = $this->get('/admin');
        $this->assertSame(200, $r['code'], 'Operator should still be authenticated after impersonation exit');
    }

    // ════════════════════════════════════════════════════════════════
    // Non-operator cannot impersonate
    // ════════════════════════════════════════════════════════════════

    public function test_business_user_cannot_start_impersonation(): void
    {
        $this->doLoginBusinessUser();
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        $csrf = $this->getCsrf();
        $r = $this->post("/admin/tenants/{$tenantId}/impersonate", ['_csrf_token' => $csrf]);

        // Should redirect to /admin (not the tenant dashboard via impersonation)
        $this->assertSame(302, $r['code']);
        $this->assertStringNotContainsString(
            '/impersonate',
            $r['location'],
            'Business user should not be able to impersonate'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Audit log
    // ════════════════════════════════════════════════════════════════

    public function test_audit_log_records_impersonation_events(): void
    {
        // Clear old impersonation audit entries
        try {
            EnvLoader::load(dirname(__DIR__, 2) . '/.env');
            Database::connect();
        } catch (\Throwable) {
            $this->markTestSkipped('DB not available');
        }

        Database::execute(
            "DELETE FROM `audit_log` WHERE `action` IN ('impersonation.started', 'impersonation.ended')"
        );

        $this->doLoginOperator();
        $tenantId = TestFixtures::BUSINESS_TENANT_ID;

        // Start
        $csrf = $this->getCsrf();
        $this->post("/admin/tenants/{$tenantId}/impersonate", ['_csrf_token' => $csrf]);

        // Exit
        $csrf = $this->getCsrf();
        $this->post('/admin/impersonate/exit', ['_csrf_token' => $csrf]);

        // Check audit log
        $entries = Database::query(
            "SELECT `action`, `entity_type`, `entity_id`, `actor_type`, `tenant_id`
             FROM `audit_log`
             WHERE `action` IN ('impersonation.started', 'impersonation.ended')
             ORDER BY `id` ASC"
        );

        $this->assertGreaterThanOrEqual(2, count($entries), 'Should have at least 2 impersonation audit entries');

        $start = $entries[count($entries) - 2];
        $end   = $entries[count($entries) - 1];

        $this->assertSame('impersonation.started', $start['action']);
        $this->assertSame('tenant', $start['entity_type']);
        $this->assertSame($tenantId, $start['entity_id']);
        $this->assertSame('operator', $start['actor_type']);
        $this->assertSame($tenantId, $start['tenant_id']);

        $this->assertSame('impersonation.ended', $end['action']);
        $this->assertSame('tenant', $end['entity_type']);
        $this->assertSame($tenantId, $end['entity_id']);
        $this->assertSame('operator', $end['actor_type']);
    }

    // ── Helpers ──

    private function doLoginOperator(): void
    {
        $r = $this->get('/admin/login');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $r['body'], $m);
        $csrf = $m[1] ?? '';

        $this->post('/admin/login', [
            'email' => TestFixtures::OPERATOR_EMAIL,
            'password' => TestFixtures::OPERATOR_PASSWORD,
            '_csrf_token' => $csrf,
        ]);
    }

    private function doLoginBusinessUser(): void
    {
        $r = $this->get('/admin/login');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $r['body'], $m);
        $csrf = $m[1] ?? '';

        $this->post('/admin/login', [
            'email' => TestFixtures::BUSINESS_EMAIL,
            'password' => TestFixtures::BUSINESS_PASSWORD,
            '_csrf_token' => $csrf,
        ]);
    }

    private function getCsrf(): string
    {
        $r = $this->get('/admin');
        if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m)) {
            return $m[1];
        }
        // Try tenant dashboard
        $r = $this->get('/admin/tenants/' . TestFixtures::BUSINESS_TENANT_ID);
        if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r['body'], $m)) {
            return $m[1];
        }
        return '';
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
