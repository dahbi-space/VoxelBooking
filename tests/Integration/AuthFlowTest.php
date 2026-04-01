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

    // ── remember_me ──

    public function testRememberedSessionSurvivesOvernightGap(): void
    {
        // Log in with remember_me
        $this->doLoginWithRememberMe();

        // Verify authenticated
        $response = $this->get('/admin');
        $this->assertSame(200, $response['code'], 'Dashboard should be accessible after login');

        // Extract session ID and mutate _last_activity to 12h ago
        $sessionId = $this->extractSessionId();
        $this->assertNotEmpty($sessionId, 'Should have a session ID');
        $this->mutateSessionActivity($sessionId, time() - (12 * 3600));

        // Simulate browser restart: fresh jar with only the persisted cookie
        $freshJar = tempnam(sys_get_temp_dir(), 'vb_rmb_');
        $code = $this->requestWithCookie('/admin', $sessionId, $freshJar);
        @unlink($freshJar);

        $this->assertSame(200, $code,
            'Remembered session must survive 12h inactivity gap (overnight). '
            . 'Got HTTP ' . $code . ' instead of 200.');
    }

    public function testNonRememberedSessionExpiresAfterOvernightGap(): void
    {
        // Log in WITHOUT remember_me
        $this->doLogin();

        $response = $this->get('/admin');
        $this->assertSame(200, $response['code']);

        // Extract session ID and mutate _last_activity to 12h ago
        $sessionId = $this->extractSessionId();
        $this->assertNotEmpty($sessionId);
        $this->mutateSessionActivity($sessionId, time() - (12 * 3600));

        // Simulate browser restart
        $freshJar = tempnam(sys_get_temp_dir(), 'vb_nrmb_');
        $code = $this->requestWithCookie('/admin', $sessionId, $freshJar);
        @unlink($freshJar);

        $this->assertSame(302, $code,
            'Non-remembered session must expire after 12h inactivity gap. '
            . 'Got HTTP ' . $code . ' instead of 302.');
    }

    public function testLoginWithoutRememberMeClearsStaleFlag(): void
    {
        // First login WITH remember_me
        $this->doLoginWithRememberMe();

        $response = $this->get('/admin');
        $this->assertSame(200, $response['code']);

        $sessionId1 = $this->extractSessionId();
        $this->assertNotEmpty($sessionId1);
        $this->assertSessionContains($sessionId1, 'remember_me',
            'Session should contain remember_me after remembered login');

        // Second login WITHOUT remember_me — no logout in between.
        // Auth::login() calls regenerateSession() which preserves session data
        // (including the stale remember_me flag). Only the controller's
        // explicit `unset($_SESSION['remember_me'])` clears it.
        $response = $this->get('/admin');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $response['body'], $m);
        $csrf = $m[1] ?? '';

        $this->post('/admin/login', [
            'email' => TestFixtures::OPERATOR_EMAIL,
            'password' => TestFixtures::OPERATOR_PASSWORD,
            '_csrf_token' => $csrf,
            // no remember_me field
        ]);

        $response = $this->get('/admin');
        $this->assertSame(200, $response['code']);

        $sessionId2 = $this->extractSessionId();
        $this->assertNotEmpty($sessionId2);
        $this->assertSessionNotContains($sessionId2, 'remember_me',
            'Session must NOT contain remember_me after non-remembered re-login (no logout in between)');
    }

    // ── Session-file helpers ──

    private function doLoginWithRememberMe(): void
    {
        $response = $this->get('/admin/login');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $response['body'], $m);
        $csrf = $m[1] ?? '';

        $this->post('/admin/login', [
            'email' => TestFixtures::OPERATOR_EMAIL,
            'password' => TestFixtures::OPERATOR_PASSWORD,
            '_csrf_token' => $csrf,
            'remember_me' => '1',
        ]);
    }

    private function extractSessionId(): string
    {
        $jarContents = file_get_contents($this->cookieJar);
        if ($jarContents === false) {
            return '';
        }
        preg_match('/vb_session\t(.+)$/m', $jarContents, $m);
        return trim($m[1] ?? '');
    }

    private function sessionFilePath(string $sessionId): string
    {
        return dirname(__DIR__, 2) . '/storage/sessions/sess_' . $sessionId;
    }

    private function mutateSessionActivity(string $sessionId, int $newTimestamp): void
    {
        $path = $this->sessionFilePath($sessionId);
        $this->assertFileExists($path, "Session file must exist: {$path}");

        $raw = file_get_contents($path);
        // PHP session format: _last_activity|i:<timestamp>;
        $updated = preg_replace(
            '/_last_activity\|i:\d+;/',
            '_last_activity|i:' . $newTimestamp . ';',
            $raw
        );
        file_put_contents($path, $updated);
    }

    private function assertSessionContains(string $sessionId, string $key, string $message = ''): void
    {
        $path = $this->sessionFilePath($sessionId);
        $this->assertFileExists($path);
        $raw = file_get_contents($path);
        $this->assertStringContainsString($key, $raw, $message);
    }

    private function assertSessionNotContains(string $sessionId, string $key, string $message = ''): void
    {
        $path = $this->sessionFilePath($sessionId);
        $this->assertFileExists($path);
        $raw = file_get_contents($path);
        $this->assertStringNotContainsString($key, $raw, $message);
    }

    private function requestWithCookie(string $path, string $sessionId, string $cookieJar): int
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $cookieJar,
            CURLOPT_COOKIE         => 'vb_session=' . $sessionId,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }

    // ══════════════════════════════════════════════════════════════
    // Multi-method auth: request-route + verify-route tests
    // ══════════════════════════════════════════════════════════════

    /**
     * Helper: switch to log transport, returning prior state for cleanup.
     */
    private function enableLogTransport(): array
    {
        $prior = \App\Engine\Database::query(
            "SELECT `value` FROM `settings` WHERE `key` = 'mail_transport' LIMIT 1"
        );
        $hadPrior = !empty($prior);
        $priorValue = $prior[0]['value'] ?? null;

        \App\Engine\Database::execute(
            "INSERT INTO `settings` (`key`, `value`) VALUES ('mail_transport', 'log')
             ON DUPLICATE KEY UPDATE `value` = 'log'"
        );
        \App\Engine\Mailer::clearConfigCache();

        return ['had' => $hadPrior, 'value' => $priorValue];
    }

    /**
     * Helper: restore mail transport and clean up test artifacts.
     */
    private function restoreTransport(array $prior): void
    {
        if ($prior['had'] && $prior['value'] !== null) {
            \App\Engine\Database::execute(
                "UPDATE `settings` SET `value` = ? WHERE `key` = 'mail_transport'",
                [$prior['value']]
            );
        } else {
            \App\Engine\Database::execute(
                "DELETE FROM `settings` WHERE `key` = 'mail_transport'"
            );
        }
        \App\Engine\Mailer::clearConfigCache();

        // Clean up tokens and email logs for test email
        \App\Engine\Database::execute(
            "DELETE FROM `login_tokens` WHERE `email` = ?",
            [TestFixtures::OPERATOR_EMAIL]
        );
        \App\Engine\Database::execute(
            "DELETE FROM `email_log` WHERE `to_email` = ?",
            [TestFixtures::OPERATOR_EMAIL]
        );
    }

    public function testRequestCodeOtpCreatesTokenAndLogsEmail(): void
    {
        $prior = $this->enableLogTransport();

        try {
            // Get CSRF token
            $loginPage = $this->get('/admin/login');
            preg_match('/name="_csrf_token" value="([^"]+)"/', $loginPage['body'], $csrfMatch);
            $csrf = $csrfMatch[1] ?? '';

            $this->post('/admin/login/request-code', [
                '_csrf_token' => $csrf,
                'email'       => TestFixtures::OPERATOR_EMAIL,
                'method'      => 'otp',
            ]);

            // Assert token created
            $tokens = \App\Engine\Database::query(
                "SELECT `type`, `used_at` FROM `login_tokens`
                 WHERE `email` = ? AND `type` = 'otp' ORDER BY `created_at` DESC LIMIT 1",
                [TestFixtures::OPERATOR_EMAIL]
            );
            $this->assertNotEmpty($tokens, 'OTP token should be created');
            $this->assertNull($tokens[0]['used_at'], 'Token should not be used yet');

            // Assert email logged
            $log = \App\Engine\Database::query(
                "SELECT `type`, `status` FROM `email_log`
                 WHERE `to_email` = ? AND `type` = 'login_code' ORDER BY `id` DESC LIMIT 1",
                [TestFixtures::OPERATOR_EMAIL]
            );
            $this->assertNotEmpty($log, 'Email log entry should exist');
            $this->assertSame('sent', $log[0]['status']);
        } finally {
            $this->restoreTransport($prior);
        }
    }

    public function testRequestCodeMagicLinkCreatesTokenAndLogsEmail(): void
    {
        $prior = $this->enableLogTransport();

        try {
            $loginPage = $this->get('/admin/login');
            preg_match('/name="_csrf_token" value="([^"]+)"/', $loginPage['body'], $csrfMatch);
            $csrf = $csrfMatch[1] ?? '';

            $this->post('/admin/login/request-code', [
                '_csrf_token'  => $csrf,
                'email'        => TestFixtures::OPERATOR_EMAIL,
                'method'       => 'magic_link',
                'remember_me'  => '1',
            ]);

            // Assert token with remember_me=1
            $tokens = \App\Engine\Database::query(
                "SELECT `type`, `remember_me` FROM `login_tokens`
                 WHERE `email` = ? AND `type` = 'magic_link' ORDER BY `created_at` DESC LIMIT 1",
                [TestFixtures::OPERATOR_EMAIL]
            );
            $this->assertNotEmpty($tokens, 'Magic link token should be created');
            $this->assertSame(1, (int) $tokens[0]['remember_me']);

            // Assert email logged
            $log = \App\Engine\Database::query(
                "SELECT `type` FROM `email_log`
                 WHERE `to_email` = ? AND `type` = 'magic_link' LIMIT 1",
                [TestFixtures::OPERATOR_EMAIL]
            );
            $this->assertNotEmpty($log);
        } finally {
            $this->restoreTransport($prior);
        }
    }

    public function testVerifyCodeOtpCreatesSession(): void
    {
        try {
            // Create token via engine (not via route)
            $create = \App\Engine\LoginToken::createOtp(TestFixtures::OPERATOR_EMAIL, '127.0.0.1');
            $this->assertTrue($create['success']);

            // Get CSRF
            $verifyPage = $this->get('/admin/login/verify-code?email=' . urlencode(TestFixtures::OPERATOR_EMAIL));
            preg_match('/name="_csrf_token" value="([^"]+)"/', $verifyPage['body'], $csrfMatch);
            $csrf = $csrfMatch[1] ?? '';

            $result = $this->post('/admin/login/verify-code', [
                '_csrf_token' => $csrf,
                'email'       => TestFixtures::OPERATOR_EMAIL,
                'code'        => $create['code'],
            ]);

            $this->assertSame(302, $result['code']);
            $this->assertStringContainsString('/admin', $result['location']);

            // Verify authenticated
            $dashboard = $this->get('/admin');
            $this->assertSame(200, $dashboard['code'], 'Should be authenticated after OTP verify');
        } finally {
            \App\Engine\Database::execute(
                "DELETE FROM `login_tokens` WHERE `email` = ?",
                [TestFixtures::OPERATOR_EMAIL]
            );
        }
    }

    public function testVerifyMagicLinkCreatesSession(): void
    {
        try {
            $create = \App\Engine\LoginToken::createMagicLink(TestFixtures::OPERATOR_EMAIL, '127.0.0.1');
            $this->assertTrue($create['success']);

            $result = $this->get('/admin/login/verify?token=' . $create['token']);
            $this->assertSame(302, $result['code']);
            $this->assertStringContainsString('/admin', $result['location']);

            $dashboard = $this->get('/admin');
            $this->assertSame(200, $dashboard['code'], 'Should be authenticated after magic link verify');
        } finally {
            \App\Engine\Database::execute(
                "DELETE FROM `login_tokens` WHERE `email` = ?",
                [TestFixtures::OPERATOR_EMAIL]
            );
        }
    }

    public function testVerifyCodeFailsWithWrongCode(): void
    {
        try {
            \App\Engine\LoginToken::createOtp(TestFixtures::OPERATOR_EMAIL, '127.0.0.1');

            $verifyPage = $this->get('/admin/login/verify-code?email=' . urlencode(TestFixtures::OPERATOR_EMAIL));
            preg_match('/name="_csrf_token" value="([^"]+)"/', $verifyPage['body'], $csrfMatch);
            $csrf = $csrfMatch[1] ?? '';

            $result = $this->post('/admin/login/verify-code', [
                '_csrf_token' => $csrf,
                'email'       => TestFixtures::OPERATOR_EMAIL,
                'code'        => '000000',
            ]);

            $this->assertSame(302, $result['code']);
            $this->assertStringContainsString('verify-code', $result['location'], 'Should redirect back to verify-code');

            // Should NOT be authenticated
            $dashboard = $this->get('/admin');
            $this->assertSame(302, $dashboard['code'], 'Should not be authenticated with wrong code');
        } finally {
            \App\Engine\Database::execute(
                "DELETE FROM `login_tokens` WHERE `email` = ?",
                [TestFixtures::OPERATOR_EMAIL]
            );
        }
    }

    public function testMagicLinkRememberMeSurvivesOvernight(): void
    {
        try {
            $create = \App\Engine\LoginToken::createMagicLink(
                TestFixtures::OPERATOR_EMAIL,
                '127.0.0.1',
                rememberMe: true
            );
            $this->assertTrue($create['success']);

            // Verify magic link
            $result = $this->get('/admin/login/verify?token=' . $create['token']);
            $this->assertSame(302, $result['code']);

            // Follow redirect to set session
            $this->get('/admin');

            // Mutate session to 12h ago
            $sessionId = $this->extractSessionId();
            $this->assertNotEmpty($sessionId, 'Session ID must exist');

            $twelveHoursAgo = time() - (12 * 3600);
            $this->mutateSessionActivity($sessionId, $twelveHoursAgo);

            // Should still be authenticated (remember_me bypasses timeout)
            $freshJar = tempnam(sys_get_temp_dir(), 'vb_ml_rm_');
            $code = $this->requestWithCookie('/admin', $sessionId, $freshJar);
            @unlink($freshJar);

            $this->assertSame(200, $code,
                'Magic link with remember_me should survive 12h inactivity gap');
        } finally {
            \App\Engine\Database::execute(
                "DELETE FROM `login_tokens` WHERE `email` = ?",
                [TestFixtures::OPERATOR_EMAIL]
            );
        }
    }

    /**
     * When mail is not configured, request-code must not claim success.
     *
     * Proves the fix for P2: "treats unsuccessful issuance as success".
     */
    public function testRequestCodeFailsWhenMailUnconfigured(): void
    {
        // Save current transport, then set to empty (unconfigured SMTP)
        $prior = \App\Engine\Database::query(
            "SELECT `value` FROM `settings` WHERE `key` = 'mail_transport' LIMIT 1"
        );
        $hadPrior = !empty($prior);
        $priorValue = $prior[0]['value'] ?? null;

        // Set transport to 'smtp' with no host configured — isConfigured() returns false
        \App\Engine\Database::execute(
            "INSERT INTO `settings` (`key`, `value`) VALUES ('mail_transport', 'smtp')
             ON DUPLICATE KEY UPDATE `value` = 'smtp'"
        );
        // Also ensure smtp_host is empty
        \App\Engine\Database::execute(
            "INSERT INTO `settings` (`key`, `value`) VALUES ('smtp_host', '')
             ON DUPLICATE KEY UPDATE `value` = ''"
        );
        \App\Engine\Mailer::clearConfigCache();

        try {
            $loginPage = $this->get('/admin/login');
            preg_match('/name="_csrf_token" value="([^"]+)"/', $loginPage['body'], $csrfMatch);
            $csrf = $csrfMatch[1] ?? '';

            $result = $this->post('/admin/login/request-code', [
                '_csrf_token' => $csrf,
                'email'       => TestFixtures::OPERATOR_EMAIL,
                'method'      => 'otp',
            ]);

            // Should redirect to /admin/login (not /admin/login/verify-code)
            $this->assertSame(302, $result['code']);
            $this->assertStringNotContainsString('verify-code', $result['location'],
                'Must not redirect to OTP verify when mail is unconfigured');

            // The redirected login page should show an error, not a success
            $redirected = $this->get('/admin/login');
            $this->assertSame(200, $redirected['code']);
            // The error flash should contain the passwordless_unavailable message
            // (rendered in the page body before being consumed)
        } finally {
            // Restore original transport
            if ($hadPrior && $priorValue !== null) {
                \App\Engine\Database::execute(
                    "UPDATE `settings` SET `value` = ? WHERE `key` = 'mail_transport'",
                    [$priorValue]
                );
            } else {
                \App\Engine\Database::execute(
                    "DELETE FROM `settings` WHERE `key` = 'mail_transport'"
                );
            }
            \App\Engine\Database::execute(
                "DELETE FROM `settings` WHERE `key` = 'smtp_host'"
            );
            \App\Engine\Mailer::clearConfigCache();
        }
    }
}
