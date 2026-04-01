<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\EnvLoader;
use App\Engine\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for tenant settings.
 *
 * Covers 5 tabs: General, Branding, Booking Rules (timeslot), Privacy, Notifications.
 *
 * - Tab access: operator on all tabs, owner on all tabs, manager 403 on GET + POST
 * - Save: general, branding, booking (timeslot), privacy, notifications
 * - Validation: empty name, invalid email, invalid notif email
 * - Old-input: general/notifications validation failure preserves user input
 * - Audit: explicit save-then-check, not inherited from prior test
 * - Sidebar: settings visible for operator, hidden for manager
 * - Slug: read-only enforcement
 * - Pattern gate: booking tab forbidden for non-timeslot tenants
 */
final class TenantSettingsTest extends TestCase
{
    private static string $baseUrl;
    private static bool $appReachable = false;
    private static bool $dbReady = false;
    private static string $setupError = '';

    private static string $tenantId = '';
    private static string $resourceTenantId = '';
    private static string $operatorCookie = '';
    private static string $operatorCsrf = '';
    private static string $ownerCookie = '';
    private static string $ownerCsrf = '';
    private static string $ownerId = '';
    private static string $managerCookie = '';
    private static string $managerCsrf = '';
    private static string $managerId = '';

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');

        $ch = curl_init(self::$baseUrl . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $result = curl_exec($ch);
        $code = ($result !== false) ? curl_getinfo($ch, CURLINFO_HTTP_CODE) : 0;
        curl_close($ch);

        if ($code === 0) {
            self::$setupError = 'App not reachable at ' . self::$baseUrl;
            return;
        }
        self::$appReachable = true;

        try {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            EnvLoader::load(dirname(__DIR__, 2) . '/.env');
            Database::connect();
            self::$dbReady = true;
        } catch (\Throwable $e) {
            self::$setupError = 'DB init failed: ' . $e->getMessage();
            return;
        }

        try {
            Database::execute('TRUNCATE TABLE `rate_limits`');
        } catch (\Throwable) {}

        try {
            TestFixtures::provision();
            self::seedData();
            self::loginOperator();
            self::createAndLoginOwner();
            self::createAndLoginManager();
        } catch (\Throwable $e) {
            self::$setupError = 'Provisioning failed: ' . $e->getMessage();
        }
    }

    protected function setUp(): void
    {
        if (!self::$appReachable) {
            $this->markTestSkipped(self::$setupError ?: 'App not reachable');
        }
        if (!self::$dbReady || self::$tenantId === '') {
            $this->markTestSkipped(self::$setupError ?: 'DB or tenant not ready');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenantId === '') return;
        try {
            if (self::$ownerId !== '') Database::execute('DELETE FROM `business_users` WHERE `id` = ?', [self::$ownerId]);
            if (self::$managerId !== '') Database::execute('DELETE FROM `business_users` WHERE `id` = ?', [self::$managerId]);
            Database::execute('DELETE FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
            if (self::$resourceTenantId !== '') Database::execute('DELETE FROM `tenants` WHERE `id` = ?', [self::$resourceTenantId]);
        } catch (\Throwable) {}
    }

    // ════════════════════════════════════════════════════════════════
    // Tab access — operator (all 4 tabs)
    // ════════════════════════════════════════════════════════════════

    public function testGeneralTabLoadsForOperator(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('ts-name', $res['body']);
    }

    public function testBrandingTabLoadsForOperator(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/branding", 'operator');
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('ts-brand-color', $res['body']);
    }

    public function testPrivacyTabLoadsForOperator(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/privacy", 'operator');
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('ts-privacy-url', $res['body']);
    }

    public function testNotificationsTabLoadsForOperator(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/notifications", 'operator');
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('ts-notif-email', $res['body']);
    }

    // ════════════════════════════════════════════════════════════════
    // Tab access — owner (all 4 tabs)
    // ════════════════════════════════════════════════════════════════

    public function testGeneralTabLoadsForOwner(): void
    {
        if (self::$ownerCookie === '') $this->markTestSkipped('Owner login not available');
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'owner');
        $this->assertSame(200, $res['code']);
    }

    public function testBrandingTabLoadsForOwner(): void
    {
        if (self::$ownerCookie === '') $this->markTestSkipped('Owner login not available');
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/branding", 'owner');
        $this->assertSame(200, $res['code']);
    }

    public function testPrivacyTabLoadsForOwner(): void
    {
        if (self::$ownerCookie === '') $this->markTestSkipped('Owner login not available');
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/privacy", 'owner');
        $this->assertSame(200, $res['code']);
    }

    public function testNotificationsTabLoadsForOwner(): void
    {
        if (self::$ownerCookie === '') $this->markTestSkipped('Owner login not available');
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/notifications", 'owner');
        $this->assertSame(200, $res['code']);
    }

    // ════════════════════════════════════════════════════════════════
    // Tab access — manager (forbidden GET + POST)
    // ════════════════════════════════════════════════════════════════

    public function testManagerForbiddenOnGeneralGet(): void
    {
        if (self::$managerCookie === '') $this->markTestSkipped('Manager login not available');
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'manager');
        $this->assertSame(403, $res['code']);
    }

    public function testManagerForbiddenOnGeneralPost(): void
    {
        if (self::$managerCookie === '') $this->markTestSkipped('Manager login not available');

        $bookings = self::httpGet("/admin/tenants/" . self::$tenantId . "/bookings", 'manager');
        self::extractCsrf($bookings['body'], 'manager');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings", [
            '_csrf_token' => self::$managerCsrf,
            'name'        => 'Hacked Name',
            'email'       => 'hacked@test.test',
        ], 'manager');
        $this->assertSame(403, $res['code']);

        // Verify no write
        $row = Database::query('SELECT `name` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame('Settings Test Tenant', $row[0]['name']);
    }

    // ════════════════════════════════════════════════════════════════
    // Save general
    // ════════════════════════════════════════════════════════════════

    public function testSaveGeneralUpdatesFields(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings", [
            '_csrf_token' => self::$operatorCsrf,
            'name'        => 'Updated Settings Name',
            'email'       => 'updated-settings@test.test',
            'timezone'    => 'Europe/Amsterdam',
            'locale'      => 'nl',
            'currency'    => 'USD',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `name`, `email`, `timezone`, `locale`, `currency` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame('Updated Settings Name', $row[0]['name']);
        $this->assertSame('updated-settings@test.test', $row[0]['email']);
        $this->assertSame('Europe/Amsterdam', $row[0]['timezone']);
        $this->assertSame('nl', $row[0]['locale']);
        $this->assertSame('USD', $row[0]['currency']);

        // Restore
        Database::execute('UPDATE `tenants` SET `name` = ?, `email` = ?, `timezone` = ?, `locale` = ?, `currency` = ? WHERE `id` = ?', [
            'Settings Test Tenant', 'settings-test@test.test', 'UTC', 'en', 'EUR', self::$tenantId
        ]);
    }

    public function testSaveGeneralRejectsEmptyName(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings", [
            '_csrf_token' => self::$operatorCsrf,
            'name'        => '',
            'email'       => 'settings-test@test.test',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `name` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame('Settings Test Tenant', $row[0]['name']);
    }

    public function testSaveGeneralRejectsInvalidEmail(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings", [
            '_csrf_token' => self::$operatorCsrf,
            'name'        => 'Settings Test Tenant',
            'email'       => 'not-an-email',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `email` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame('settings-test@test.test', $row[0]['email']);
    }

    // ════════════════════════════════════════════════════════════════
    // Old-input preservation on validation failure
    // ════════════════════════════════════════════════════════════════

    public function testGeneralOldInputPreservedOnValidationFailure(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        self::extractCsrf($page['body'], 'operator');

        // Submit invalid (empty name) but with custom phone/timezone
        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings", [
            '_csrf_token' => self::$operatorCsrf,
            'name'        => '',
            'email'       => 'valid@test.test',
            'phone'       => '+31612345678',
            'timezone'    => 'Europe/Berlin',
            'locale'      => 'de',
            'currency'    => 'GBP',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        // Follow redirect — form must show the old input, not the persisted values
        $form = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        $this->assertSame(200, $form['code']);

        $body = $form['body'];
        $this->assertStringContainsString('+31612345678', $body, 'Phone old input must be preserved');
        $this->assertStringContainsString('Europe/Berlin', $body, 'Timezone old input must be preserved');
        $this->assertStringContainsString('value="de"', $body, 'Locale old input must be preserved');
        $this->assertStringContainsString('value="GBP"', $body, 'Currency old input must be preserved');
    }

    public function testNotificationsOldInputPreservedOnValidationFailure(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/notifications", 'operator');
        self::extractCsrf($page['body'], 'operator');

        // Submit invalid email
        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/notifications", [
            '_csrf_token'            => self::$operatorCsrf,
            'notification_email'     => 'not-valid',
            'notify_on_booking'      => '1',
            'notify_on_cancellation' => '0',
            'send_reminders'         => '1',
            'reminder_hours_before'  => '8',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        // Follow redirect
        $form = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/notifications", 'operator');
        $this->assertSame(200, $form['code']);

        $body = $form['body'];
        $this->assertStringContainsString('not-valid', $body, 'Invalid email must be preserved in form');
        $this->assertStringContainsString('value="8"', $body, 'Reminder hours old input must be preserved');
    }

    // ════════════════════════════════════════════════════════════════
    // Save branding
    // ════════════════════════════════════════════════════════════════

    public function testSaveBrandingUpdatesFields(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/branding", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/branding", [
            '_csrf_token'              => self::$operatorCsrf,
            'brand_color'              => '#10B981',
            'brand_color_text'         => '#000000',
            'booking_page_heading'     => 'Welcome!',
            'booking_page_description' => 'Book your appointment.',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `brand_color`, `brand_color_text`, `booking_page_heading`, `booking_page_description` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame('#10B981', $row[0]['brand_color']);
        $this->assertSame('#000000', $row[0]['brand_color_text']);
        $this->assertSame('Welcome!', $row[0]['booking_page_heading']);
        $this->assertSame('Book your appointment.', $row[0]['booking_page_description']);
    }

    // ════════════════════════════════════════════════════════════════
    // Save privacy
    // ════════════════════════════════════════════════════════════════

    public function testSavePrivacyUpdatesFields(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/privacy", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/privacy", [
            '_csrf_token'        => self::$operatorCsrf,
            'requires_consent'   => '1',
            'privacy_policy_url' => 'https://example.com/privacy',
            'consent_text'       => 'I agree to the terms.',
            'data_retention_months' => '12',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `requires_consent`, `privacy_policy_url`, `consent_text`, `data_retention_months` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame(1, (int) $row[0]['requires_consent']);
        $this->assertSame('https://example.com/privacy', $row[0]['privacy_policy_url']);
        $this->assertSame('I agree to the terms.', $row[0]['consent_text']);
        $this->assertSame(12, (int) $row[0]['data_retention_months']);
    }

    // ════════════════════════════════════════════════════════════════
    // Save notifications
    // ════════════════════════════════════════════════════════════════

    public function testSaveNotificationsUpdatesFields(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/notifications", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/notifications", [
            '_csrf_token'            => self::$operatorCsrf,
            'notification_email'     => 'alerts@test.test',
            'notify_on_booking'      => '1',
            'notify_on_cancellation' => '0',
            'send_reminders'         => '1',
            'reminder_hours_before'  => '4',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `notification_email`, `notify_on_booking`, `notify_on_cancellation`, `send_reminders`, `reminder_hours_before` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame('alerts@test.test', $row[0]['notification_email']);
        $this->assertSame(1, (int) $row[0]['notify_on_booking']);
        $this->assertSame(0, (int) $row[0]['notify_on_cancellation']);
        $this->assertSame(1, (int) $row[0]['send_reminders']);
        $this->assertSame(4, (int) $row[0]['reminder_hours_before']);
    }

    public function testSaveNotificationsRejectsInvalidEmail(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/notifications", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/notifications", [
            '_csrf_token'        => self::$operatorCsrf,
            'notification_email' => 'bad-email',
            'notify_on_booking'  => '1',
            'notify_on_cancellation' => '1',
            'send_reminders'     => '1',
            'reminder_hours_before' => '24',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `notification_email` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame('alerts@test.test', $row[0]['notification_email'], 'Must be unchanged on invalid');
    }

    // ════════════════════════════════════════════════════════════════
    // Audit logging (self-contained)
    // ════════════════════════════════════════════════════════════════

    public function testAuditLogCreatedOnPrivacySave(): void
    {
        // Clean audit for this tenant
        Database::execute(
            "DELETE FROM `audit_log` WHERE `action` = 'tenant.settings_updated' AND `entity_id` = ?",
            [self::$tenantId]
        );

        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/privacy", 'operator');
        self::extractCsrf($page['body'], 'operator');

        // Trigger a real change
        $current = Database::query('SELECT `data_retention_months` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $newVal = ((int) $current[0]['data_retention_months']) === 12 ? '36' : '12';

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/privacy", [
            '_csrf_token'           => self::$operatorCsrf,
            'requires_consent'      => '1',
            'data_retention_months' => $newVal,
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $audit = Database::query(
            "SELECT * FROM `audit_log` WHERE `action` = 'tenant.settings_updated' AND `entity_id` = ? ORDER BY `created_at` DESC LIMIT 1",
            [self::$tenantId]
        );
        $this->assertNotEmpty($audit, 'Audit log for settings_updated must be created');
        $details = json_decode($audit[0]['details'], true);
        $this->assertSame('privacy', $details['tab'] ?? null, 'Audit must record the tab');
    }

    // ════════════════════════════════════════════════════════════════
    // Sidebar visibility
    // ════════════════════════════════════════════════════════════════

    public function testSidebarShowsSettingsForOperator(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('/settings"', $res['body']);
    }

    public function testSidebarHidesSettingsForManager(): void
    {
        if (self::$managerCookie === '') $this->markTestSkipped('Manager login not available');
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/bookings", 'manager');
        if ($res['code'] === 200) {
            $this->assertStringNotContainsString('/settings"', $res['body']);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Slug read-only
    // ════════════════════════════════════════════════════════════════

    public function testSlugNotChangedByGeneralSave(): void
    {
        $before = Database::query('SELECT `slug` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $originalSlug = $before[0]['slug'];

        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings", [
            '_csrf_token' => self::$operatorCsrf,
            'name'        => 'Settings Test Tenant',
            'email'       => 'settings-test@test.test',
        ], 'operator');

        $after = Database::query('SELECT `slug` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame($originalSlug, $after[0]['slug']);
    }

    // ════════════════════════════════════════════════════════════════
    // Booking Rules (timeslot pattern only)
    // ════════════════════════════════════════════════════════════════

    public function testBookingTabLoadsForTimeslotOperator(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/booking", 'operator');
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('ts-slot-duration', $res['body']);
    }

    public function testBookingTabLoadsForTimeslotOwner(): void
    {
        if (self::$ownerCookie === '') $this->markTestSkipped('Owner login not available');
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/booking", 'owner');
        $this->assertSame(200, $res['code']);
    }

    public function testBookingTabForbiddenForManager(): void
    {
        if (self::$managerCookie === '') $this->markTestSkipped('Manager login not available');
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/booking", 'manager');
        $this->assertSame(403, $res['code']);
    }

    public function testBookingTabForbiddenForNonTimeslotTenant(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$resourceTenantId . "/settings/booking", 'operator');
        $this->assertSame(403, $res['code']);
    }

    public function testBookingPostForbiddenForManager(): void
    {
        if (self::$managerCookie === '') $this->markTestSkipped('Manager login not available');

        $before = Database::query(
            'SELECT `slot_duration_minutes`, `buffer_minutes`, `min_advance_hours`, `max_advance_days` FROM `tenants` WHERE `id` = ?',
            [self::$tenantId]
        );

        // Grab a CSRF token from a page the manager can access
        $bookings = self::httpGet("/admin/tenants/" . self::$tenantId . "/bookings", 'manager');
        self::extractCsrf($bookings['body'], 'manager');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/booking", [
            '_csrf_token'           => self::$managerCsrf,
            'slot_duration_minutes' => '99',
            'buffer_minutes'        => '99',
            'min_advance_hours'     => '99',
            'max_advance_days'      => '999',
        ], 'manager');

        $this->assertSame(403, $res['code']);

        $after = Database::query(
            'SELECT `slot_duration_minutes`, `buffer_minutes`, `min_advance_hours`, `max_advance_days` FROM `tenants` WHERE `id` = ?',
            [self::$tenantId]
        );
        $this->assertSame($before[0], $after[0], 'Booking fields must be unchanged after manager POST');
    }

    public function testBookingPostForbiddenForNonTimeslotTenant(): void
    {
        $before = Database::query(
            'SELECT `slot_duration_minutes`, `buffer_minutes`, `min_advance_hours`, `max_advance_days` FROM `tenants` WHERE `id` = ?',
            [self::$resourceTenantId]
        );

        $page = self::httpGet("/admin/tenants/" . self::$resourceTenantId . "/settings", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$resourceTenantId . "/settings/booking", [
            '_csrf_token'           => self::$operatorCsrf,
            'slot_duration_minutes' => '99',
            'buffer_minutes'        => '99',
            'min_advance_hours'     => '99',
            'max_advance_days'      => '999',
        ], 'operator');

        $this->assertSame(403, $res['code']);

        $after = Database::query(
            'SELECT `slot_duration_minutes`, `buffer_minutes`, `min_advance_hours`, `max_advance_days` FROM `tenants` WHERE `id` = ?',
            [self::$resourceTenantId]
        );
        $this->assertSame($before[0], $after[0], 'Booking fields must be unchanged after non-timeslot POST');
    }

    public function testSaveBookingUpdatesAllFields(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/booking", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/booking", [
            '_csrf_token'           => self::$operatorCsrf,
            'slot_duration_minutes' => '45',
            'buffer_minutes'        => '10',
            'min_advance_hours'     => '2',
            'max_advance_days'      => '60',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `slot_duration_minutes`, `buffer_minutes`, `min_advance_hours`, `max_advance_days` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame(45, (int) $row[0]['slot_duration_minutes']);
        $this->assertSame(10, (int) $row[0]['buffer_minutes']);
        $this->assertSame(2, (int) $row[0]['min_advance_hours']);
        $this->assertSame(60, (int) $row[0]['max_advance_days']);
    }

    public function testSlotDurationClampedToMin5(): void
    {
        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/booking", 'operator');
        self::extractCsrf($page['body'], 'operator');

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/booking", [
            '_csrf_token'           => self::$operatorCsrf,
            'slot_duration_minutes' => '0',
            'buffer_minutes'        => '0',
            'min_advance_hours'     => '0',
            'max_advance_days'      => '0',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $row = Database::query('SELECT `slot_duration_minutes`, `max_advance_days` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $this->assertSame(5, (int) $row[0]['slot_duration_minutes'], 'slot_duration must be clamped to min 5');
        $this->assertSame(1, (int) $row[0]['max_advance_days'], 'max_advance must be clamped to min 1');
    }

    public function testBookingAuditLogCreated(): void
    {
        Database::execute(
            "DELETE FROM `audit_log` WHERE `action` = 'tenant.settings_updated' AND `entity_id` = ?",
            [self::$tenantId]
        );

        $page = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings/booking", 'operator');
        self::extractCsrf($page['body'], 'operator');

        // Trigger a real change
        $current = Database::query('SELECT `slot_duration_minutes` FROM `tenants` WHERE `id` = ?', [self::$tenantId]);
        $newVal = ((int) $current[0]['slot_duration_minutes']) === 30 ? '45' : '30';

        $res = self::httpPost("/admin/tenants/" . self::$tenantId . "/settings/booking", [
            '_csrf_token'           => self::$operatorCsrf,
            'slot_duration_minutes' => $newVal,
            'buffer_minutes'        => '0',
            'min_advance_hours'     => '1',
            'max_advance_days'      => '90',
        ], 'operator');

        $this->assertSame(302, $res['code']);

        $audit = Database::query(
            "SELECT * FROM `audit_log` WHERE `action` = 'tenant.settings_updated' AND `entity_id` = ? ORDER BY `created_at` DESC LIMIT 1",
            [self::$tenantId]
        );
        $this->assertNotEmpty($audit);
        $details = json_decode($audit[0]['details'], true);
        $this->assertSame('booking', $details['tab'] ?? null);
    }

    public function testTabNavShowsBookingForTimeslotTenant(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$tenantId . "/settings", 'operator');
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('tab-booking', $res['body'], 'Booking tab must appear for timeslot tenant');
    }

    public function testTabNavHidesBookingForNonTimeslotTenant(): void
    {
        $res = self::httpGet("/admin/tenants/" . self::$resourceTenantId . "/settings", 'operator');
        $this->assertSame(200, $res['code']);
        $this->assertStringNotContainsString('tab-booking', $res['body'], 'Booking tab must not appear for resource tenant');
    }

    // ════════════════════════════════════════════════════════════════
    // Seed data
    // ════════════════════════════════════════════════════════════════

    private static function seedData(): void
    {
        self::$tenantId = Ulid::generate();
        self::$resourceTenantId = Ulid::generate();

        Database::execute(
            "INSERT INTO `tenants`
             (`id`, `slug`, `name`, `email`, `booking_pattern`, `status`, `brand_color`, `timezone`, `currency`)
             VALUES (?, ?, 'Settings Test Tenant', 'settings-test@test.test', 'timeslot', 'active', '#2563EB', 'UTC', 'EUR')",
            [self::$tenantId, 'test-settings-' . substr(self::$tenantId, -8)]
        );

        Database::execute(
            "INSERT INTO `tenants`
             (`id`, `slug`, `name`, `email`, `booking_pattern`, `status`, `brand_color`, `timezone`, `currency`)
             VALUES (?, ?, 'Resource Test Tenant', 'resource-test@test.test', 'resource', 'active', '#2563EB', 'UTC', 'EUR')",
            [self::$resourceTenantId, 'test-resource-' . substr(self::$resourceTenantId, -8)]
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Auth helpers
    // ════════════════════════════════════════════════════════════════

    private static function loginOperator(): void
    {
        $loginPage = self::httpGet('/admin/login', 'operator');
        self::extractCsrf($loginPage['body'], 'operator');
        $res = self::httpPost('/admin/login', [
            '_csrf_token' => self::$operatorCsrf,
            'email'       => TestFixtures::OPERATOR_EMAIL,
            'password'    => TestFixtures::OPERATOR_PASSWORD,
        ], 'operator');
        if (preg_match('/vb_session=([^;]+)/', $res['headers'], $m)) {
            self::$operatorCookie = 'vb_session=' . $m[1];
        }
        $dashboard = self::httpGet('/admin', 'operator');
        self::extractCsrf($dashboard['body'], 'operator');
    }

    private static function createAndLoginOwner(): void
    {
        $email = 'test-ts-owner-' . bin2hex(random_bytes(4)) . '@test.test';
        $password = 'OwnerTsTest123!';
        self::$ownerId = Ulid::generate();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        Database::execute(
            "INSERT INTO `business_users` (`id`, `tenant_id`, `name`, `email`, `password_hash`, `role`, `is_active`, `force_password_change`)
             VALUES (?, ?, 'TS Test Owner', ?, ?, 'owner', 1, 0)",
            [self::$ownerId, self::$tenantId, $email, $hash]
        );

        $loginPage = self::httpGet('/admin/login', 'owner');
        self::extractCsrf($loginPage['body'], 'owner');
        $res = self::httpPost('/admin/login', [
            '_csrf_token' => self::$ownerCsrf,
            'email'       => $email,
            'password'    => $password,
        ], 'owner');
        if (preg_match('/vb_session=([^;]+)/', $res['headers'], $m)) {
            self::$ownerCookie = 'vb_session=' . $m[1];
        }
        $dashboard = self::httpGet('/admin', 'owner');
        self::extractCsrf($dashboard['body'], 'owner');
    }

    private static function createAndLoginManager(): void
    {
        $email = 'test-ts-mgr-' . bin2hex(random_bytes(4)) . '@test.test';
        $password = 'MgrTsTest123!';
        self::$managerId = Ulid::generate();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        Database::execute(
            "INSERT INTO `business_users` (`id`, `tenant_id`, `name`, `email`, `password_hash`, `role`, `is_active`, `force_password_change`)
             VALUES (?, ?, 'TS Test Manager', ?, ?, 'manager', 1, 0)",
            [self::$managerId, self::$tenantId, $email, $hash]
        );

        $loginPage = self::httpGet('/admin/login', 'manager');
        self::extractCsrf($loginPage['body'], 'manager');
        $res = self::httpPost('/admin/login', [
            '_csrf_token' => self::$managerCsrf,
            'email'       => $email,
            'password'    => $password,
        ], 'manager');
        if (preg_match('/vb_session=([^;]+)/', $res['headers'], $m)) {
            self::$managerCookie = 'vb_session=' . $m[1];
        }
        $dashboard = self::httpGet('/admin', 'manager');
        self::extractCsrf($dashboard['body'], 'manager');
    }

    private static function extractCsrf(string $html, string $role): void
    {
        if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $m)) {
            match ($role) {
                'operator' => self::$operatorCsrf = $m[1],
                'owner'    => self::$ownerCsrf = $m[1],
                'manager'  => self::$managerCsrf = $m[1],
                default    => null,
            };
        }
    }

    // ════════════════════════════════════════════════════════════════
    // HTTP
    // ════════════════════════════════════════════════════════════════

    /** @return array{code: int, headers: string, body: string} */
    private static function httpGet(string $path, string $role = 'operator'): array
    {
        return self::http('GET', $path, [], $role);
    }

    /** @return array{code: int, headers: string, body: string} */
    private static function httpPost(string $path, array $data, string $role = 'operator'): array
    {
        return self::http('POST', $path, $data, $role);
    }

    /** @return array{code: int, headers: string, body: string} */
    private static function http(string $method, string $path, array $data = [], string $role = 'operator'): array
    {
        $cookie = match ($role) {
            'owner'   => self::$ownerCookie,
            'manager' => self::$managerCookie,
            default   => self::$operatorCookie,
        };

        $ch = curl_init(self::$baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);

        $headers = [];
        if ($cookie) $headers[] = 'Cookie: ' . $cookie;
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $responseHeaders = substr($response, 0, $headerSize);
        if (preg_match('/vb_session=([^;]+)/', $responseHeaders, $m)) {
            match ($role) {
                'owner'   => self::$ownerCookie = 'vb_session=' . $m[1],
                'manager' => self::$managerCookie = 'vb_session=' . $m[1],
                default   => self::$operatorCookie = 'vb_session=' . $m[1],
            };
        }

        return [
            'code'    => $code,
            'headers' => $responseHeaders,
            'body'    => substr($response, $headerSize),
        ];
    }
}
