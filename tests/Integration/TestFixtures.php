<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\EnvLoader;

/**
 * Shared test fixtures for integration tests.
 *
 * Provisions deterministic operator and business-user accounts via direct
 * database operations. Self-contained: does not depend on install wizard
 * credentials or manual database mutations.
 *
 * Uses DELETE + INSERT to guarantee fresh state on every test run,
 * regardless of pre-existing data.
 *
 * Credentials:
 *   Operator:      operator@example.com / welcome3210
 *   Business user: business@example.com / welcome3210  (tenant-scoped)
 */
final class TestFixtures
{
    public const OPERATOR_EMAIL = 'operator@example.com';
    public const OPERATOR_PASSWORD = 'welcome3210';
    public const OPERATOR_ID = '01TESTOPERATOR0000000000';

    public const BUSINESS_EMAIL = 'business@example.com';
    public const BUSINESS_PASSWORD = 'welcome3210';
    public const BUSINESS_USER_ID = '01TESTBUSINESSUSR0000000';
    public const BUSINESS_TENANT_ID = '01TESTTENANT000000000000';

    public const CUSTOMER_ID = '01TESTCUSTOMER0000000000';
    public const BOOKING_ID  = '01TESTBOOKING00000000000';

    private static bool $provisioned = false;

    /**
     * Ensure the test operator, business user, and their tenant exist
     * with exactly the expected credentials and bindings.
     *
     * Deletes any prior records for these IDs/emails before inserting,
     * so stale hashes or tenant bindings are impossible.
     */
    public static function provision(): void
    {
        if (self::$provisioned) {
            return;
        }

        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        EnvLoader::load(dirname(__DIR__, 2) . '/.env');
        Database::connect();

        $hash = password_hash(self::OPERATOR_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12]);

        // ── Operator: delete by ID and email, then insert fresh ──
        Database::execute(
            "DELETE FROM `operators` WHERE `id` = ? OR `email` = ?",
            [self::OPERATOR_ID, self::OPERATOR_EMAIL]
        );
        Database::execute(
            "INSERT INTO `operators` (`id`, `name`, `email`, `password_hash`)
             VALUES (?, 'Test Operator', ?, ?)",
            [self::OPERATOR_ID, self::OPERATOR_EMAIL, $hash]
        );

        // ── Tenant: delete by ID and slug, then insert fresh ──
        // Business user FK cascades from tenant, so delete tenant first cleans both
        Database::execute(
            "DELETE FROM `business_users` WHERE `id` = ? OR `email` = ?",
            [self::BUSINESS_USER_ID, self::BUSINESS_EMAIL]
        );
        Database::execute(
            "DELETE FROM `tenants` WHERE `id` = ? OR `slug` = 'test-fixture'",
            [self::BUSINESS_TENANT_ID]
        );
        Database::execute(
            "INSERT INTO `tenants`
             (`id`, `name`, `slug`, `email`, `booking_pattern`, `timezone`, `currency`, `brand_color`, `status`)
             VALUES (?, 'Test Tenant', 'test-fixture', ?, 'timeslot', 'UTC', 'EUR', '#2563EB', 'active')",
            [self::BUSINESS_TENANT_ID, self::BUSINESS_EMAIL]
        );

        // ── Business user: insert with known hash and tenant binding ──
        Database::execute(
            "INSERT INTO `business_users`
             (`id`, `tenant_id`, `name`, `email`, `password_hash`, `role`, `is_active`)
             VALUES (?, ?, 'Test Business User', ?, ?, 'owner', 1)",
            [self::BUSINESS_USER_ID, self::BUSINESS_TENANT_ID, self::BUSINESS_EMAIL, $hash]
        );

        // ── Customer + booking: delete then insert fresh ──
        Database::execute(
            "DELETE FROM `bookings` WHERE `id` = ?",
            [self::BOOKING_ID]
        );
        Database::execute(
            "DELETE FROM `customers` WHERE `id` = ?",
            [self::CUSTOMER_ID]
        );
        Database::execute(
            "INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`)
             VALUES (?, ?, 'Test Customer', 'customer@example.com')",
            [self::CUSTOMER_ID, self::BUSINESS_TENANT_ID]
        );
        Database::execute(
            "INSERT INTO `bookings`
             (`id`, `tenant_id`, `booking_pattern`, `customer_id`,
              `start_datetime`, `end_datetime`, `status`, `source`)
             VALUES (?, ?, 'timeslot', ?,
              DATE_ADD(CURDATE(), INTERVAL 1 DAY),
              DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL 30 MINUTE),
              'confirmed', 'web')",
            [self::BOOKING_ID, self::BUSINESS_TENANT_ID, self::CUSTOMER_ID]
        );

        // Clear rate limits for clean test runs
        Database::execute('TRUNCATE TABLE `rate_limits`');

        self::$provisioned = true;
    }

    /**
     * Get the tenant ID for the business user fixture.
     */
    public static function businessTenantId(): string
    {
        return self::BUSINESS_TENANT_ID;
    }
}
