<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\BookingService;
use App\Engine\Database;
use App\Engine\EnvLoader;
use App\Engine\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for BookingService consent evidence recording.
 *
 * Verifies end-to-end:
 * - Booking creation with consent records the exact text + timestamp
 * - Booking creation without consent (when not required) leaves consent NULL
 * - recordConsent() captures consent on existing booking
 * - hasConsent() correctly reflects consent state
 * - DataExporter includes consent records in the export
 * - Privacy endpoint surfaces consent records
 */
final class ConsentRecordingTest extends TestCase
{
    private static bool $dbConnected = false;
    private array $cleanupIds = [];

    protected function setUp(): void
    {
        if (!self::$dbConnected) {
            try {
                require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
                EnvLoader::load(dirname(__DIR__, 2) . '/.env');
                Database::connect();
                self::$dbConnected = true;
            } catch (\Throwable $e) {
                $this->markTestSkipped('Database not available: ' . $e->getMessage());
            }
        }

        // Verify tables exist
        if (!Database::tableExists('bookings') || !Database::tableExists('customers') || !Database::tableExists('tenants')) {
            $this->markTestSkipped('Required tables not present');
        }
    }

    protected function tearDown(): void
    {
        // Clean up test data in reverse dependency order
        foreach (array_reverse($this->cleanupIds) as [$table, $id]) {
            try {
                Database::execute("DELETE FROM `{$table}` WHERE `id` = ?", [$id]);
            } catch (\Throwable) {
                // Best-effort cleanup
            }
        }
    }

    /**
     * Creating a booking WITH consent records the exact consent text + timestamp.
     */
    public function testBookingWithConsentRecordsEvidence(): void
    {
        $ids = $this->seedTenantAndCustomer([
            'requires_consent' => 1,
            'consent_text' => 'I agree to data processing for my appointment.',
            'privacy_policy_url' => 'https://salon.test/privacy',
        ]);

        $result = BookingService::createBooking(
            [
                'tenant_id' => $ids['tenant_id'],
                'customer_id' => $ids['customer_id'],
                'booking_pattern' => 'timeslot',
                'start_datetime' => '2026-06-15 10:00:00',
                'end_datetime' => '2026-06-15 10:30:00',
            ],
            [
                'requires_consent' => 1,
                'consent_text' => 'I agree to data processing for my appointment.',
                'privacy_policy_url' => 'https://salon.test/privacy',
            ],
            consentGiven: true
        );

        $this->assertTrue($result['consent_recorded']);
        $this->cleanupIds[] = ['bookings', $result['id']];

        // Verify the record in the database
        $rows = Database::query(
            'SELECT `consent_given_at`, `consent_text_shown` FROM `bookings` WHERE `id` = ?',
            [$result['id']]
        );

        $this->assertCount(1, $rows);
        $this->assertNotNull($rows[0]['consent_given_at'], 'consent_given_at must be set');
        $this->assertSame(
            'I agree to data processing for my appointment. Privacy policy: https://salon.test/privacy',
            $rows[0]['consent_text_shown']
        );
    }

    /**
     * Creating a booking WITHOUT consent (when not required) leaves consent fields NULL.
     */
    public function testBookingWithoutConsentLeavesFieldsNull(): void
    {
        $ids = $this->seedTenantAndCustomer(['requires_consent' => 0]);

        $result = BookingService::createBooking(
            [
                'tenant_id' => $ids['tenant_id'],
                'customer_id' => $ids['customer_id'],
                'booking_pattern' => 'timeslot',
                'start_datetime' => '2026-06-15 11:00:00',
                'end_datetime' => '2026-06-15 11:30:00',
            ],
            ['requires_consent' => 0],
            consentGiven: false
        );

        $this->assertFalse($result['consent_recorded']);
        $this->cleanupIds[] = ['bookings', $result['id']];

        $rows = Database::query(
            'SELECT `consent_given_at`, `consent_text_shown` FROM `bookings` WHERE `id` = ?',
            [$result['id']]
        );

        $this->assertNull($rows[0]['consent_given_at']);
        $this->assertNull($rows[0]['consent_text_shown']);
    }

    /**
     * recordConsent() adds consent evidence to an existing booking.
     */
    public function testRecordConsentOnExistingBooking(): void
    {
        $ids = $this->seedTenantAndCustomer(['requires_consent' => 0]);

        // Create booking without consent
        $result = BookingService::createBooking(
            [
                'tenant_id' => $ids['tenant_id'],
                'customer_id' => $ids['customer_id'],
                'booking_pattern' => 'timeslot',
                'start_datetime' => '2026-06-15 12:00:00',
                'end_datetime' => '2026-06-15 12:30:00',
                'source' => 'admin',
            ],
            ['requires_consent' => 0],
            consentGiven: false
        );
        $this->cleanupIds[] = ['bookings', $result['id']];
        $this->assertFalse(BookingService::hasConsent($result['id']));

        // Now record consent after the fact
        BookingService::recordConsent($result['id'], [
            'consent_text' => 'Post-hoc consent.',
            'privacy_policy_url' => '',
        ]);

        $this->assertTrue(BookingService::hasConsent($result['id']));

        $rows = Database::query(
            'SELECT `consent_text_shown` FROM `bookings` WHERE `id` = ?',
            [$result['id']]
        );
        $this->assertSame('Post-hoc consent.', $rows[0]['consent_text_shown']);
    }

    /**
     * recordConsent() does NOT overwrite existing consent (idempotent).
     */
    public function testRecordConsentDoesNotOverwrite(): void
    {
        $ids = $this->seedTenantAndCustomer(['requires_consent' => 1, 'consent_text' => 'Original consent.']);

        $result = BookingService::createBooking(
            [
                'tenant_id' => $ids['tenant_id'],
                'customer_id' => $ids['customer_id'],
                'booking_pattern' => 'timeslot',
                'start_datetime' => '2026-06-15 13:00:00',
                'end_datetime' => '2026-06-15 13:30:00',
            ],
            ['requires_consent' => 1, 'consent_text' => 'Original consent.', 'privacy_policy_url' => ''],
            consentGiven: true
        );
        $this->cleanupIds[] = ['bookings', $result['id']];

        // Attempt to record consent again with different text
        BookingService::recordConsent($result['id'], [
            'consent_text' => 'Updated consent text.',
            'privacy_policy_url' => '',
        ]);

        // Original consent should be preserved
        $rows = Database::query(
            'SELECT `consent_text_shown` FROM `bookings` WHERE `id` = ?',
            [$result['id']]
        );
        $this->assertSame('Original consent.', $rows[0]['consent_text_shown']);
    }

    /**
     * DataExporter surfaces consent records from bookings.
     */
    public function testDataExporterIncludesConsentRecords(): void
    {
        $ids = $this->seedTenantAndCustomer([
            'requires_consent' => 1,
            'consent_text' => 'Export test consent.',
        ]);

        $result = BookingService::createBooking(
            [
                'tenant_id' => $ids['tenant_id'],
                'customer_id' => $ids['customer_id'],
                'booking_pattern' => 'timeslot',
                'start_datetime' => '2026-06-15 14:00:00',
                'end_datetime' => '2026-06-15 14:30:00',
            ],
            ['requires_consent' => 1, 'consent_text' => 'Export test consent.', 'privacy_policy_url' => ''],
            consentGiven: true
        );
        $this->cleanupIds[] = ['bookings', $result['id']];

        $export = \App\Engine\DataExporter::export($ids['customer_id']);

        $this->assertArrayHasKey('consent_records', $export);
        $this->assertNotEmpty($export['consent_records']);

        $consentRecord = $export['consent_records'][0];
        $this->assertArrayHasKey('consent_given_at', $consentRecord);
        $this->assertSame('Export test consent.', $consentRecord['consent_text_shown']);
    }

    // ── Helpers ──

    /**
     * @return array{tenant_id: string, customer_id: string}
     */
    private function seedTenantAndCustomer(array $tenantOverrides = []): array
    {
        $tenantId = Ulid::generate();
        $customerId = Ulid::generate();
        $slug = 'test-consent-' . substr($tenantId, -6);

        $defaults = [
            'requires_consent' => 1,
            'consent_text' => null,
            'privacy_policy_url' => null,
        ];
        $tenantData = array_merge($defaults, $tenantOverrides);

        Database::execute(
            "INSERT INTO `tenants` (`id`, `slug`, `name`, `email`, `booking_pattern`, `requires_consent`, `consent_text`, `privacy_policy_url`)
             VALUES (?, ?, 'Consent Test', 'test@example.com', 'timeslot', ?, ?, ?)",
            [$tenantId, $slug, (string) $tenantData['requires_consent'], $tenantData['consent_text'], $tenantData['privacy_policy_url']]
        );
        $this->cleanupIds[] = ['tenants', $tenantId];

        Database::execute(
            "INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`) VALUES (?, ?, 'Consent Tester', 'consent-test@example.com')",
            [$customerId, $tenantId]
        );
        $this->cleanupIds[] = ['customers', $customerId];

        return ['tenant_id' => $tenantId, 'customer_id' => $customerId];
    }
}
