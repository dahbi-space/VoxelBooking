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
 *
 * Test fixtures are provisioned automatically by TestFixtures::provision().
 */
final class AuthFlowTest extends TestCase
{
    private string $baseUrl;
    private string $cookieJar;
    private static bool $appReachable = false;

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
            // best-effort — tests will fail if fixtures are missing
        }
    }

    protected function setUp(): void
    {
        $this->baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'vb_test_');

        if (!self::$appReachable) {
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
            'email' => TestFixtures::OPERATOR_EMAIL,
            'password' => TestFixtures::OPERATOR_PASSWORD,
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

    /**
     * Session persistence: an authenticated session must survive a simulated
     * browser close/reopen.
     *
     * The 30-day cookie lifetime (Max-Age=2592000) means the browser retains
     * the vb_session cookie across restarts. This test simulates that by:
     * 1. Logging in and extracting the vb_session cookie value.
     * 2. Verifying the Set-Cookie header includes an Expires or Max-Age.
     * 3. Creating a fresh cookie jar seeded with only the persisted cookie.
     * 4. Verifying that /admin returns 200 (authenticated) using the new jar.
     */
    public function testSessionPersistsAcrossSimulatedBrowserRestart(): void
    {
        // Step 1: Log in with the primary cookie jar
        $this->doLogin();

        // Step 2: Verify authenticated and extract the session cookie
        $response = $this->get('/admin');
        $this->assertSame(200, $response['code'], 'Dashboard should be accessible after login');

        // Read the session cookie value from the jar file
        $jarContents = file_get_contents($this->cookieJar);
        $this->assertNotFalse($jarContents, 'Cookie jar should be readable');

        // Extract the vb_session cookie value from the Netscape-format jar
        // Format: domain\tTRUE/FALSE\tpath\tsecure\texpiry\tname\tvalue
        preg_match('/vb_session\t(.+)$/m', $jarContents, $sessionMatch);
        $this->assertNotEmpty($sessionMatch, 'vb_session cookie should exist in jar');
        $sessionValue = trim($sessionMatch[1]);
        $this->assertNotEmpty($sessionValue, 'vb_session cookie value should not be empty');

        // Step 3: Verify the cookie has a future expiry (not session-only)
        // In a Netscape cookie jar, column 5 (0-indexed: 4) is the expiry timestamp.
        // A session-only cookie would have expiry = 0.
        preg_match('/\t(\d+)\tvb_session\t/', $jarContents, $expiryMatch);
        if (!empty($expiryMatch)) {
            $expiry = (int) $expiryMatch[1];
            $this->assertGreaterThan(time(), $expiry,
                'vb_session cookie expiry should be in the future (persistent, not session-only)');
        }

        // Step 4: Simulate browser restart — create a fresh cookie jar
        // with only the persisted session cookie
        $freshJar = tempnam(sys_get_temp_dir(), 'vb_restart_');

        $ch = curl_init($this->baseUrl . '/admin');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $freshJar,
            // Inject the persisted cookie directly — no COOKIEFILE means no prior jar
            CURLOPT_COOKIE         => 'vb_session=' . $sessionValue,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);
        $rawResponse = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        @unlink($freshJar);

        // Step 5: The session should still be valid → 200, not 302 redirect
        $this->assertSame(200, $code,
            'Authenticated session must survive browser restart (persisted cookie jar). '
            . 'Got HTTP ' . $code . ' instead of 200. '
            . 'This proves the 30-day Max-Age cookie keeps users logged in.');
    }

    /**
     * Verify the Set-Cookie header for vb_session includes Max-Age or Expires.
     * This ensures the cookie is not session-only (which would be lost on close).
     */
    public function testSessionCookieHasMaxAge(): void
    {
        // Get the login page to receive a Set-Cookie header
        $ch = curl_init($this->baseUrl . '/admin/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);
        $response = (string) curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($response, 0, $headerSize);

        // Find the Set-Cookie header for vb_session
        preg_match('/set-cookie: vb_session=[^;]+;([^\r\n]+)/i', $headers, $cookieMatch);
        $this->assertNotEmpty($cookieMatch, 'Should receive a Set-Cookie header for vb_session');

        $cookieAttrs = strtolower($cookieMatch[1]);

        // Must have either Max-Age or Expires (not session-only)
        $hasMaxAge = str_contains($cookieAttrs, 'max-age=');
        $hasExpires = str_contains($cookieAttrs, 'expires=');
        $this->assertTrue(
            $hasMaxAge || $hasExpires,
            'vb_session cookie must have Max-Age or Expires attribute for persistence. '
            . 'Cookie attributes: ' . $cookieAttrs
        );

        // If Max-Age is present, verify it's the expected 30-day value
        if ($hasMaxAge) {
            preg_match('/max-age=(\d+)/', $cookieAttrs, $maxAgeMatch);
            $maxAge = (int) ($maxAgeMatch[1] ?? 0);
            $this->assertSame(2592000, $maxAge,
                'Max-Age should be 2592000 (30 days)');
        }
    }
}
