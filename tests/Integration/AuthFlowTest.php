<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the authentication flow.
 *
 * These tests hit the real HTTP endpoints via curl to verify
 * session handling, login/logout, and redirect behavior.
 *
 * Requires:
 * - The app running at APP_TEST_URL (defaults to https://voxelbooking-app.test)
 * - A valid operator account (operator@example.com / welcome3210)
 */
final class AuthFlowTest extends TestCase
{
    private string $baseUrl;
    private string $cookieJar;

    protected function setUp(): void
    {
        $this->baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'vb_test_');

        // Check if the app is reachable
        $ch = curl_init($this->baseUrl . '/health');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 5]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !str_contains((string) $r, 'VoxelBooking')) {
            $this->markTestSkipped('App not reachable at ' . $this->baseUrl);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->cookieJar);
    }

    /**
     * Blocker 2 regression: authenticated users visiting /admin/login
     * must be redirected (302) to /admin, not shown the login form.
     */
    public function testAuthenticatedUserRedirectedFromLoginPage(): void
    {
        // Log in
        $this->doLogin();

        // Verify we're authenticated
        $response = $this->get('/admin');
        $this->assertSame(200, $response['code'], 'Dashboard should be accessible after login');

        // Visit login page while authenticated → must redirect
        $response = $this->get('/admin/login');
        $this->assertSame(302, $response['code'], 'Authenticated user should be redirected from login page');
        $this->assertStringContainsString('/admin', $response['location'], 'Should redirect to /admin');
    }

    /**
     * Session fixation protection: session ID must change after login.
     */
    public function testSessionIdChangesAfterLogin(): void
    {
        // Get initial session cookie
        $response = $this->get('/admin/login');
        $this->assertSame(200, $response['code']);
        $preLoginSession = $response['cookies']['vb_session'] ?? '';
        $this->assertNotEmpty($preLoginSession, 'Should receive a session cookie');

        // Log in
        $this->doLogin();

        // Get post-login session cookie
        $response = $this->get('/admin');
        $postLoginSession = $response['cookies']['vb_session'] ?? '';

        // Session ID should have changed (fixation protection)
        $this->assertNotSame($preLoginSession, $postLoginSession,
            'Session ID must change after login (fixation protection)');
    }

    /**
     * Unauthenticated access to /admin must redirect to /admin/login.
     */
    public function testUnauthenticatedAdminRedirect(): void
    {
        $response = $this->get('/admin');
        $this->assertSame(302, $response['code']);
        $this->assertStringContainsString('/admin/login', $response['location']);
    }

    /**
     * Admin 404 should render within the admin shell (has sidebar).
     */
    public function testAdmin404UsesAdminShell(): void
    {
        $this->doLogin();

        $response = $this->get('/admin/nonexistent-page');
        $this->assertSame(404, $response['code']);
        $this->assertStringContainsString('sidebar', $response['body'],
            'Admin 404 should render inside admin layout with sidebar');
    }

    /**
     * Public 404 should NOT use admin shell.
     */
    public function testPublic404IsStandalone(): void
    {
        $response = $this->get('/nonexistent-page');
        $this->assertSame(404, $response['code']);
        $this->assertStringContainsString('Page not found', $response['body']);
    }

    /**
     * Logout destroys session — subsequent /admin should redirect.
     */
    public function testLogoutDestroysSession(): void
    {
        $this->doLogin();

        // Verify authenticated
        $response = $this->get('/admin');
        $this->assertSame(200, $response['code']);

        // Get CSRF token from dashboard
        preg_match('/name="_csrf_token" value="([^"]+)"/', $response['body'], $m);
        $csrf = $m[1] ?? '';
        $this->assertNotEmpty($csrf, 'Should have CSRF token');

        // Logout
        $response = $this->post('/auth/logout', ['_csrf_token' => $csrf]);
        $this->assertSame(302, $response['code']);

        // Verify no longer authenticated
        $response = $this->get('/admin');
        $this->assertSame(302, $response['code'], 'Should redirect after logout');
        $this->assertStringContainsString('/admin/login', $response['location']);
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
     * @return array{code: int, body: string, location: string, cookies: array<string, string>}
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

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        preg_match('/location: (.+)/i', $headers, $loc);
        $cookies = $this->parseCookies($headers);

        return [
            'code'     => $code,
            'body'     => $body,
            'location' => trim($loc[1] ?? ''),
            'cookies'  => $cookies,
        ];
    }

    /**
     * @return array{code: int, body: string, location: string, cookies: array<string, string>}
     */
    private function post(string $path, array $data): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HEADER => true,
        ]);
        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        preg_match('/location: (.+)/i', $headers, $loc);
        $cookies = $this->parseCookies($headers);

        return [
            'code'     => $code,
            'body'     => $body,
            'location' => trim($loc[1] ?? ''),
            'cookies'  => $cookies,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseCookies(string $headers): array
    {
        $cookies = [];
        preg_match_all('/set-cookie: ([^=]+)=([^;]+)/i', $headers, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $cookies[trim($m[1])] = trim($m[2]);
        }
        return $cookies;
    }
}
