<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\EnvLoader;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for tenant creation with optional first-owner.
 *
 * Covers:
 * - Tenant-only creation (no owner) — backward compat
 * - Tenant + owner creation in one transaction
 * - Owner can log in and gets redirected to their tenant
 * - Invalid owner email → tenant still fails validation, not partial commit
 * - Duplicate owner email → error, no orphan records
 *
 * Requires: The app running at APP_TEST_URL (defaults to https://voxelbooking-app.test).
 */
final class TenantOwnerCreationTest extends TestCase
{
    private string $baseUrl;
    private string $csrfToken = '';
    private string $sessionCookie = '';

    /** @var list<array{string, string}> */
    private array $cleanupIds = [];

    private static bool $appReachable = false;
    private static bool $dbReady = false;

    public static function setUpBeforeClass(): void
    {
        $baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');

        $ch = curl_init($baseUrl . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 5,
        ]);
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
            Database::query('SELECT 1');
            self::$dbReady = true;
        } catch (\Throwable) {
            return;
        }
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

        // Login as operator
        $this->operatorLogin();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanupIds) as [$table, $id]) {
            try {
                Database::execute("DELETE FROM `{$table}` WHERE `id` = ?", [$id]);
            } catch (\Throwable) {
                // best-effort
            }
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Tests
    // ════════════════════════════════════════════════════════════════

    /**
     * Creating a tenant without owner fields works as before (backward compat).
     */
    public function testCreateTenantWithoutOwner(): void
    {
        $slug = 'test-no-owner-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $res = $this->postForm('/admin/tenants/create', [
            'name'            => 'No Owner Tenant',
            'slug'            => $slug,
            'email'           => 'noowner@test.test',
            'booking_pattern' => 'timeslot',
            'timezone'        => 'UTC',
            'currency'        => 'EUR',
            'brand_color'     => '#2563EB',
        ]);

        // Should redirect to tenant list
        $this->assertRedirect($res, '/admin/tenants');

        // Verify tenant was created
        $rows = Database::query('SELECT `id` FROM `tenants` WHERE `slug` = ?', [$slug]);
        $this->assertCount(1, $rows);
        $this->cleanupIds[] = ['tenants', $rows[0]['id']];

        // Verify no business user was created
        $users = Database::query(
            'SELECT `id` FROM `business_users` WHERE `tenant_id` = ?',
            [$rows[0]['id']]
        );
        $this->assertEmpty($users, 'No business user should be created when owner fields are empty');
    }

    /**
     * Creating a tenant with owner fields creates both in one transaction.
     */
    public function testCreateTenantWithOwner(): void
    {
        $slug = 'test-with-owner-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $ownerEmail = 'owner-' . $slug . '@test.test';

        $res = $this->postForm('/admin/tenants/create', [
            'name'              => 'Owner Test Tenant',
            'slug'              => $slug,
            'email'             => 'tenant-' . $slug . '@test.test',
            'booking_pattern'   => 'timeslot',
            'timezone'          => 'Europe/Amsterdam',
            'currency'          => 'EUR',
            'brand_color'       => '#059669',
            'create_owner'      => '1',
            'owner_name'        => 'Test Owner',
            'owner_email'       => $ownerEmail,
            'owner_password'    => 'TestPass123!',
            'send_owner_email'  => '0', // Don't attempt to send email
        ]);

        $this->assertRedirect($res, '/admin/tenants');

        // Verify tenant
        $tenants = Database::query('SELECT `id` FROM `tenants` WHERE `slug` = ?', [$slug]);
        $this->assertCount(1, $tenants, 'Tenant must be created');
        $tenantId = $tenants[0]['id'];
        $this->cleanupIds[] = ['tenants', $tenantId];

        // Verify business user
        $users = Database::query(
            'SELECT `id`, `role`, `force_password_change`, `is_active` FROM `business_users` WHERE `tenant_id` = ? AND `email` = ?',
            [$tenantId, $ownerEmail]
        );
        $this->assertCount(1, $users, 'Owner business user must be created');
        $this->cleanupIds[] = ['business_users', $users[0]['id']];

        $this->assertSame('owner', $users[0]['role'], 'First owner must have role=owner');
        $this->assertEquals(1, $users[0]['force_password_change'], 'force_password_change must be 1');
        $this->assertEquals(1, $users[0]['is_active'], 'Owner must be active');
    }

    /**
     * The created owner can log in and gets redirected to their tenant dashboard.
     */
    public function testOwnerCanLoginAndRedirect(): void
    {
        $slug = 'test-login-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $ownerEmail = 'logintest-' . $slug . '@test.test';
        $ownerPass = 'LoginPass456!';

        // Create tenant + owner
        $this->postForm('/admin/tenants/create', [
            'name'            => 'Login Test Tenant',
            'slug'            => $slug,
            'email'           => 'tenant-' . $slug . '@test.test',
            'booking_pattern' => 'timeslot',
            'timezone'        => 'UTC',
            'currency'        => 'EUR',
            'brand_color'     => '#2563EB',
            'create_owner'    => '1',
            'owner_name'      => 'Login Owner',
            'owner_email'     => $ownerEmail,
            'owner_password'  => $ownerPass,
        ]);

        $tenants = Database::query('SELECT `id` FROM `tenants` WHERE `slug` = ?', [$slug]);
        $this->assertNotEmpty($tenants);
        $tenantId = $tenants[0]['id'];
        $this->cleanupIds[] = ['tenants', $tenantId];

        $users = Database::query('SELECT `id` FROM `business_users` WHERE `tenant_id` = ?', [$tenantId]);
        $this->assertNotEmpty($users);
        $this->cleanupIds[] = ['business_users', $users[0]['id']];

        // Log out current operator session
        $this->request('GET', '/admin/logout');

        // Login as the new owner
        $loginRes = $this->loginAs($ownerEmail, $ownerPass);

        // Should redirect to the tenant dashboard
        $this->assertStringContainsString(
            "/admin/tenants/{$tenantId}",
            $loginRes['headers'],
            'Business user login must redirect to their tenant dashboard'
        );
    }

    /**
     * Missing owner name with create_owner=1 returns a validation error.
     */
    public function testMissingOwnerNameReturnsError(): void
    {
        $slug = 'test-noname-' . substr(bin2hex(random_bytes(4)), 0, 8);

        $res = $this->postForm('/admin/tenants/create', [
            'name'            => 'No Name Test',
            'slug'            => $slug,
            'email'           => 'noname@test.test',
            'booking_pattern' => 'timeslot',
            'create_owner'    => '1',
            'owner_name'      => '', // missing
            'owner_email'     => 'owner-noname@test.test',
        ]);

        // Should redirect back to create form (not to tenant list)
        $this->assertRedirect($res, '/admin/tenants/create');

        // Verify no tenant was created
        $rows = Database::query('SELECT `id` FROM `tenants` WHERE `slug` = ?', [$slug]);
        $this->assertEmpty($rows, 'No tenant should be created when owner validation fails');
    }

    /**
     * Missing owner email with create_owner=1 targets the owner_email field,
     * NOT the tenant email field. Old input must be preserved.
     */
    public function testMissingOwnerEmailTargetsOwnerField(): void
    {
        $slug = 'test-nomail-' . substr(bin2hex(random_bytes(4)), 0, 8);

        $res = $this->postForm('/admin/tenants/create', [
            'name'            => 'Owner No Email',
            'slug'            => $slug,
            'email'           => 'tenant-' . $slug . '@test.test',
            'booking_pattern' => 'timeslot',
            'timezone'        => 'UTC',
            'currency'        => 'EUR',
            'brand_color'     => '#2563EB',
            'create_owner'    => '1',
            'owner_name'      => 'Jane Doe',
            'owner_email'     => '', // missing
        ]);

        // Should redirect back to create form
        $this->assertRedirect($res, '/admin/tenants/create');

        // Follow redirect to inspect the rendered form
        $page = $this->request('GET', '/admin/tenants/create');
        $body = $page['body'];

        // Old input must be preserved
        $this->assertStringContainsString('Owner No Email', $body,
            'Old tenant name must be preserved on redirect');
        $this->assertStringContainsString('tenant-' . $slug . '@test.test', $body,
            'Old tenant email must be preserved on redirect');

        // The tenant email input must NOT have is-invalid
        $this->assertDoesNotMatchRegularExpression(
            '/id="tenant_email"[^>]*class="[^"]*is-invalid/',
            $body,
            'Tenant email must NOT be marked invalid when owner email is missing'
        );

        // The owner email input MUST have is-invalid
        $this->assertMatchesRegularExpression(
            '/id="owner_email"[^>]*class="[^"]*is-invalid/',
            $body,
            'Owner email must be marked invalid when owner email is missing'
        );

        // The owner-specific error message must appear
        $this->assertStringContainsString('Owner email is required', $body,
            'Owner email required error message must appear on the page');

        // Verify no tenant was created
        $rows = Database::query('SELECT `id` FROM `tenants` WHERE `slug` = ?', [$slug]);
        $this->assertEmpty($rows, 'No tenant should be created when owner email is missing');
    }

    /**
     * Duplicate owner email targets the owner_email field and preserves old input.
     */
    public function testDuplicateOwnerEmailTargetsOwnerField(): void
    {
        // First, create a tenant with an owner so the email is taken
        $slug1 = 'test-dup1-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $ownerEmail = 'dup-' . $slug1 . '@test.test';

        $this->postForm('/admin/tenants/create', [
            'name'            => 'First Tenant',
            'slug'            => $slug1,
            'email'           => 'tenant-' . $slug1 . '@test.test',
            'booking_pattern' => 'timeslot',
            'create_owner'    => '1',
            'owner_name'      => 'First Owner',
            'owner_email'     => $ownerEmail,
            'owner_password'  => 'Pass1234!',
        ]);

        $tenants = Database::query('SELECT `id` FROM `tenants` WHERE `slug` = ?', [$slug1]);
        $this->assertNotEmpty($tenants, 'First tenant must be created');
        $this->cleanupIds[] = ['tenants', $tenants[0]['id']];
        $users = Database::query('SELECT `id` FROM `business_users` WHERE `tenant_id` = ?', [$tenants[0]['id']]);
        if (!empty($users)) {
            $this->cleanupIds[] = ['business_users', $users[0]['id']];
        }

        // Now try to create a second tenant with the same owner email
        $slug2 = 'test-dup2-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $res = $this->postForm('/admin/tenants/create', [
            'name'            => 'Second Tenant',
            'slug'            => $slug2,
            'email'           => 'tenant-' . $slug2 . '@test.test',
            'booking_pattern' => 'timeslot',
            'create_owner'    => '1',
            'owner_name'      => 'Dup Owner',
            'owner_email'     => $ownerEmail, // duplicate
            'owner_password'  => 'Pass5678!',
        ]);

        // Should redirect back to create form
        $this->assertRedirect($res, '/admin/tenants/create');

        // Follow redirect
        $page = $this->request('GET', '/admin/tenants/create');
        $body = $page['body'];

        // Old input preserved
        $this->assertStringContainsString('Second Tenant', $body,
            'Old tenant name must be preserved on duplicate owner email redirect');

        // Owner email field must be marked invalid (not tenant email)
        $this->assertMatchesRegularExpression(
            '/id="owner_email"[^>]*class="[^"]*is-invalid/',
            $body,
            'Owner email must be marked invalid on duplicate email'
        );

        // Verify no second tenant was created
        $rows = Database::query('SELECT `id` FROM `tenants` WHERE `slug` = ?', [$slug2]);
        $this->assertEmpty($rows, 'No tenant should be created when owner email is taken');
    }

    // ════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════

    private function operatorLogin(): void
    {
        // Get the login page to extract CSRF token
        $loginPage = $this->request('GET', '/admin/login');
        $this->extractCsrf($loginPage['body']);

        // Login as operator
        $loginRes = $this->request('POST', '/admin/login', [
            '_csrf_token' => $this->csrfToken,
            'email'       => 'operator@example.com',
            'password'    => 'welcome3210',
        ]);

        // Extract session cookie from response
        if (preg_match('/vb_session=([^;]+)/', $loginRes['headers'], $m)) {
            $this->sessionCookie = 'vb_session=' . $m[1];
        }

        // Follow redirect to get new CSRF token
        $dashboard = $this->request('GET', '/admin');
        $this->extractCsrf($dashboard['body']);
    }

    private function loginAs(string $email, string $password): array
    {
        // Get login page for CSRF
        $loginPage = $this->request('GET', '/admin/login');
        $this->extractCsrf($loginPage['body']);

        return $this->request('POST', '/admin/login', [
            '_csrf_token' => $this->csrfToken,
            'email'       => $email,
            'password'    => $password,
        ]);
    }

    private function postForm(string $path, array $data): array
    {
        // Get the page first to refresh CSRF
        $page = $this->request('GET', $path);
        $this->extractCsrf($page['body']);
        $data['_csrf_token'] = $this->csrfToken;

        return $this->request('POST', $path, $data);
    }

    private function extractCsrf(string $html): void
    {
        if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m)) {
            $this->csrfToken = $m[1];
        }
    }

    private function assertRedirect(array $response, string $expectedPath): void
    {
        $this->assertGreaterThanOrEqual(300, $response['code']);
        $this->assertLessThan(400, $response['code']);
        $this->assertStringContainsString(
            $expectedPath,
            $response['headers'],
            "Expected redirect to {$expectedPath}"
        );
    }

    /**
     * @return array{code: int, headers: string, body: string}
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);

        $headers = [];
        if ($this->sessionCookie) {
            $headers[] = 'Cookie: ' . $this->sessionCookie;
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $responseHeaders = substr($response, 0, $headerSize);

        // Track cookies for session persistence
        if (preg_match('/vb_session=([^;]+)/', $responseHeaders, $m)) {
            $this->sessionCookie = 'vb_session=' . $m[1];
        }

        return [
            'code'    => $code,
            'headers' => $responseHeaders,
            'body'    => substr($response, $headerSize),
        ];
    }
}
