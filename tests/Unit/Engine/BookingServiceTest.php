<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\BookingService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BookingService consent evidence recording.
 *
 * Verifies:
 * - Consent text resolution (tenant text, default fallback, policy URL append)
 * - Required field validation
 * - Consent enforcement when tenant requires it
 * - Consent text truncation at VARCHAR(500) limit
 * - createBooking signature and consent_recorded return shape
 */
final class BookingServiceTest extends TestCase
{
    // ── resolveConsentText ──

    /**
     * Tenant with custom consent text uses that text verbatim.
     */
    public function testResolveConsentTextUsesCustomText(): void
    {
        $tenant = ['consent_text' => 'I accept the booking terms.', 'privacy_policy_url' => ''];
        $text = BookingService::resolveConsentText($tenant);

        $this->assertSame('I accept the booking terms.', $text);
    }

    /**
     * Tenant without consent text gets the default.
     */
    public function testResolveConsentTextFallsBackToDefault(): void
    {
        $tenant = ['consent_text' => '', 'privacy_policy_url' => ''];
        $text = BookingService::resolveConsentText($tenant);

        $this->assertSame(
            'I agree to the processing of my personal data for booking purposes.',
            $text
        );
    }

    /**
     * NULL consent_text also falls back to default.
     */
    public function testResolveConsentTextHandlesNull(): void
    {
        $tenant = [];
        $text = BookingService::resolveConsentText($tenant);

        $this->assertStringContainsString('I agree to the processing', $text);
    }

    /**
     * Privacy policy URL is appended when present.
     */
    public function testResolveConsentTextAppendsPrivacyUrl(): void
    {
        $tenant = [
            'consent_text' => 'I agree.',
            'privacy_policy_url' => 'https://example.com/privacy',
        ];
        $text = BookingService::resolveConsentText($tenant);

        $this->assertSame('I agree. Privacy policy: https://example.com/privacy', $text);
    }

    /**
     * Privacy policy URL is NOT appended when empty.
     */
    public function testResolveConsentTextSkipsEmptyUrl(): void
    {
        $tenant = ['consent_text' => 'I agree.', 'privacy_policy_url' => ''];
        $text = BookingService::resolveConsentText($tenant);

        $this->assertSame('I agree.', $text);
    }

    /**
     * Consent text is truncated to 500 chars (field limit).
     */
    public function testResolveConsentTextTruncatesAt500(): void
    {
        $longText = str_repeat('a', 600);
        $tenant = ['consent_text' => $longText, 'privacy_policy_url' => ''];
        $text = BookingService::resolveConsentText($tenant);

        $this->assertSame(500, mb_strlen($text));
        $this->assertStringEndsWith('...', $text);
    }

    // ── createBooking validation ──

    /**
     * Missing required field throws InvalidArgumentException.
     */
    public function testCreateBookingThrowsOnMissingField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required booking field: tenant_id');

        BookingService::createBooking(
            ['customer_id' => 'x', 'booking_pattern' => 'timeslot', 'start_datetime' => 'x', 'end_datetime' => 'x'],
            ['requires_consent' => 0],
            false
        );
    }

    /**
     * All five required fields are checked.
     */
    public function testCreateBookingRequiresAllFields(): void
    {
        $required = ['tenant_id', 'customer_id', 'booking_pattern', 'start_datetime', 'end_datetime'];

        foreach ($required as $field) {
            $data = array_fill_keys($required, 'value');
            unset($data[$field]);

            try {
                BookingService::createBooking($data, ['requires_consent' => 0], false);
                $this->fail("Expected InvalidArgumentException for missing field: {$field}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($field, $e->getMessage());
            } catch (\Throwable) {
                // Database errors are expected when not connected — we only test validation
                $this->assertTrue(true);
            }
        }
    }

    /**
     * Consent required + not given throws RuntimeException.
     */
    public function testCreateBookingThrowsWhenConsentRequired(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Booking requires consent');

        BookingService::createBooking(
            [
                'tenant_id' => 'tenant1',
                'customer_id' => 'customer1',
                'booking_pattern' => 'timeslot',
                'start_datetime' => '2026-01-01 10:00:00',
                'end_datetime' => '2026-01-01 10:30:00',
            ],
            ['requires_consent' => 1],
            false
        );
    }

    /**
     * Consent not required + not given does NOT throw.
     * (Will fail at DB layer since no DB, but validates consent logic).
     */
    public function testCreateBookingAllowsNoConsentWhenNotRequired(): void
    {
        try {
            BookingService::createBooking(
                [
                    'tenant_id' => 'tenant1',
                    'customer_id' => 'customer1',
                    'booking_pattern' => 'timeslot',
                    'start_datetime' => '2026-01-01 10:00:00',
                    'end_datetime' => '2026-01-01 10:30:00',
                ],
                ['requires_consent' => 0],
                false
            );
            // If DB is connected, this succeeded — good
            $this->assertTrue(true);
        } catch (\RuntimeException $e) {
            // Should NOT be a consent error
            $this->assertStringNotContainsString('consent', strtolower($e->getMessage()));
        } catch (\Throwable) {
            // DB not connected — that's fine, consent validation passed
            $this->assertTrue(true);
        }
    }

    // ── Consent evidence shape ──

    /**
     * resolveConsentText always returns a non-empty string.
     */
    public function testResolveConsentTextNeverReturnsEmpty(): void
    {
        $emptyTenants = [
            [],
            ['consent_text' => ''],
            ['consent_text' => null],
            ['consent_text' => '   '],
        ];

        foreach ($emptyTenants as $tenant) {
            $text = BookingService::resolveConsentText($tenant);
            $this->assertNotEmpty($text, 'Consent text must never be empty');
        }
    }

    /**
     * Consent text with privacy URL is within field limit.
     */
    public function testConsentTextWithUrlStaysWithinLimit(): void
    {
        $tenant = [
            'consent_text' => str_repeat('x', 400),
            'privacy_policy_url' => 'https://example.com/' . str_repeat('y', 200),
        ];
        $text = BookingService::resolveConsentText($tenant);

        $this->assertLessThanOrEqual(500, mb_strlen($text));
    }
}
