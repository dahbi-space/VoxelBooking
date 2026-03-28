<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\EnvLoader;

/**
 * Shared test fixtures for integration tests.
 *
 * Provisions deterministic operator and business user accounts via direct
 * database operations. Self-contained: does not depend on install wizard
 * credentials or manual database mutations.
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

    private static bool $provisioned = false;

    /**
     * Ensure the test operator, business user, and their tenant exist.
     *
     * Safe to call multiple times — uses INSERT IGNORE to avoid duplicates.
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

        // Operator account
        Database::execute(
            "INSERT IGNORE INTO `operators` (`id`, `name`, `email`, `password_hash`)
             VALUES (?, 'Test Operator', ?, ?)",
            [self::OPERATOR_ID, self::OPERATOR_EMAIL, $hash]
        );

        // Tenant for the business user
        Database::execute(
            "INSERT IGNORE INTO `tenants`
             (`id`, `name`, `slug`, `email`, `booking_pattern`, `timezone`, `currency`, `brand_color`, `status`)
             VALUES (?, 'Test Tenant', 'test-fixture', ?, 'timeslot', 'UTC', 'EUR', '#2563EB', 'active')",
            [self::BUSINESS_TENANT_ID, self::BUSINESS_EMAIL]
        );

        // Business user account
        Database::execute(
            "INSERT IGNORE INTO `business_users`
             (`id`, `tenant_id`, `name`, `email`, `password_hash`, `role`, `is_active`)
             VALUES (?, ?, 'Test Business User', ?, ?, 'owner', 1)",
            [self::BUSINESS_USER_ID, self::BUSINESS_TENANT_ID, self::BUSINESS_EMAIL, $hash]
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
