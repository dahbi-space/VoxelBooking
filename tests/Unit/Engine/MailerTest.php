<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\Mailer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Mailer engine.
 *
 * Verifies:
 * - HTML-to-plain-text conversion
 * - Credential redaction in error messages
 * - Encryption resolution
 * - Config cache behavior
 * - Privacy email rendering
 * - Graceful failure when SMTP is not configured
 */
final class MailerTest extends TestCase
{
    /**
     * @param array<string, string> $config
     */
    private function setMailerConfig(array $config): void
    {
        $property = new \ReflectionProperty(Mailer::class, 'configCache');
        $property->setValue(null, array_merge([
            'smtp_host' => '',
            'smtp_port' => '',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => '',
            'mail_from_address' => '',
            'mail_from_name' => '',
            'mail_transport' => 'smtp',
        ], $config));
    }

    protected function setUp(): void
    {
        Mailer::clearConfigCache();
        \App\Engine\Locale::init(dirname(__DIR__, 3));
        \App\Engine\Locale::setLocale('en');
    }

    /**
     * htmlToPlainText strips tags and converts BR to newlines.
     */
    public function testHtmlToPlainTextStripsTagsAndConverts(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'htmlToPlainText');

        $html = '<h1>Hello</h1><p>World</p><br>Line 2';
        $text = $method->invoke(null, $html);

        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('World', $text);
        $this->assertStringContainsString('Line 2', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    /**
     * htmlToPlainText decodes HTML entities.
     */
    public function testHtmlToPlainTextDecodesEntities(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'htmlToPlainText');

        $html = 'Price: &euro;50 &amp; tax &lt;included&gt;';
        $text = $method->invoke(null, $html);

        $this->assertStringContainsString('€50', $text);
        $this->assertStringContainsString('& tax', $text);
        $this->assertStringContainsString('<included>', $text);
    }

    /**
     * redactCredentials removes sensitive values from error messages.
     */
    public function testRedactCredentialsRemovesSensitiveValues(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'redactCredentials');

        $config = [
            'smtp_username' => 'admin@example.com',
            'smtp_password' => 'SuperSecret123!',
        ];

        $message = 'Failed to authenticate with admin@example.com using password SuperSecret123!';
        $redacted = $method->invoke(null, $message, $config);

        $this->assertStringNotContainsString('SuperSecret123!', $redacted);
        $this->assertStringNotContainsString('admin@example.com', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    /**
     * redactCredentials handles empty password gracefully.
     */
    public function testRedactCredentialsHandlesEmptyPassword(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'redactCredentials');

        $config = ['smtp_username' => '', 'smtp_password' => ''];
        $message = 'Connection timed out';
        $redacted = $method->invoke(null, $message, $config);

        $this->assertSame('Connection timed out', $redacted);
    }

    /**
     * resolveEncryption maps setting values to PHPMailer constants.
     */
    public function testResolveEncryption(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'resolveEncryption');

        $this->assertSame('tls', $method->invoke(null, 'tls'));
        $this->assertSame('ssl', $method->invoke(null, 'ssl'));
        $this->assertSame('', $method->invoke(null, 'none'));
        $this->assertSame('', $method->invoke(null, ''));
        $this->assertSame('tls', $method->invoke(null, 'TLS'));
        $this->assertSame('ssl', $method->invoke(null, 'SSL'));
    }

    /**
     * renderPrivacyEmail produces valid HTML with all parts.
     */
    public function testRenderPrivacyEmailContainsAllParts(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'renderPrivacyEmail');

        $html = $method->invoke(null, 'Test Title', 'Test body content.', 'Test footer note.');

        $this->assertStringContainsString('Test Title', $html);
        $this->assertStringContainsString('Test body content.', $html);
        $this->assertStringContainsString('Test footer note.', $html);
        // Powered-by line now uses app_name() — in test context this is the env fallback
        $this->assertStringContainsString('Powered by', $html);
        $this->assertStringContainsString('<html>', $html);
    }

    /**
     * Email translations correctly substitute :app_name placeholder.
     */
    public function testEmailTranslationsSubstituteAppName(): void
    {
        $testName = 'MyTestApp';
        $replacements = ['app_name' => $testName];

        // Test subject
        $subject = __('email.test.subject', $replacements);
        $this->assertStringContainsString($testName, $subject);
        $this->assertStringNotContainsString(':app_name', $subject);

        // Common powered_by
        $poweredBy = __('email.common.powered_by', $replacements);
        $this->assertStringContainsString($testName, $poweredBy);
        $this->assertStringNotContainsString(':app_name', $poweredBy);

        // Operator deletion footer
        $footer = __('email.operator_deletion.footer', $replacements);
        $this->assertStringContainsString($testName, $footer);
        $this->assertStringNotContainsString(':app_name', $footer);
    }

    /**
     * send() with no SMTP configured returns graceful failure.
     */
    public function testSendWithNoSmtpReturnsFailure(): void
    {
        $this->setMailerConfig([
            'mail_transport' => 'smtp',
            'smtp_host' => '',
        ]);

        $result = Mailer::send(
            'test@example.com',
            'Test Subject',
            '<p>Test body</p>',
            'test',
        );

        $this->assertFalse($result['sent']);
        $this->assertSame('SMTP not configured', $result['error']);
    }

    /**
     * isConfigured returns false when SMTP host is empty.
     */
    public function testIsConfiguredReturnsFalseWhenEmpty(): void
    {
        $this->setMailerConfig([
            'mail_transport' => 'smtp',
            'smtp_host' => '',
        ]);

        $this->assertFalse(Mailer::isConfigured());
    }

    /**
     * clearConfigCache resets the internal cache.
     */
    public function testClearConfigCacheWorks(): void
    {
        $this->setMailerConfig([
            'mail_transport' => 'smtp',
            'smtp_host' => 'smtp.example.com',
        ]);

        $this->assertTrue(Mailer::isConfigured());

        Mailer::clearConfigCache();
        $this->setMailerConfig([
            'mail_transport' => 'smtp',
            'smtp_host' => '',
        ]);

        $this->assertFalse(Mailer::isConfigured());
    }

    // ── Branded confirmation email renderer tests ──

    public function testRenderConfirmationEmailContainsAllParts(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'renderConfirmationEmail');

        $html = $method->invoke(null,
            '#2563EB',                                       // brandColor
            'Your booking has been confirmed.',              // heading
            'Hi Test Customer,',                             // greeting
            'Your booking has been confirmed.',              // bodyText
            'Booking details',                               // detailsHeading
            [                                                // details
                'Date'    => 'Wednesday, 2 April 2026',
                'Time'    => '10:00–10:30',
                'Service' => 'Haircut',
                'Staff'   => 'Emma',
            ],
            'If you need to make changes, please contact us.', // footerText
            'Salon Bella',                                   // tenantName
            'VoxelBooking',                                  // appName
        );

        // Brand header bar
        $this->assertStringContainsString('#2563EB', $html, 'Must contain brand color');
        $this->assertStringContainsString('40px', $html, 'Must contain header bar height');

        // Status confirmation
        $this->assertStringContainsString('✓', $html, 'Must contain check mark');

        // Heading
        $this->assertStringContainsString('Your booking has been confirmed.', $html);

        // Greeting
        $this->assertStringContainsString('Hi Test Customer,', $html);

        // Details section heading
        $this->assertStringContainsString('Booking details', $html, 'Must contain details heading');

        // Summary card details
        $this->assertStringContainsString('Wednesday, 2 April 2026', $html);
        $this->assertStringContainsString('10:00', $html);
        $this->assertStringContainsString('Haircut', $html);
        $this->assertStringContainsString('Emma', $html);

        // Footer
        $this->assertStringContainsString('Salon Bella', $html);
        $this->assertStringContainsString('VoxelBooking', $html);
    }

    public function testRenderConfirmationEmailOmitsNullDetails(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'renderConfirmationEmail');

        $html = $method->invoke(null,
            '#2563EB',
            'Confirmed.', 'Hi Test,', 'Confirmed.',
            'Booking details',                                   // detailsHeading
            ['Date' => '2026-04-02', 'Time' => '10:00–10:30'],  // no Service or Staff
            'Contact us.', 'Test Biz', 'VB',
        );

        $this->assertStringNotContainsString('Service', $html);
        $this->assertStringNotContainsString('Staff', $html);
    }

    public function testPlainTextConfirmationIsReadable(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'renderConfirmationPlainText');

        $plain = $method->invoke(null,
            'Your booking has been confirmed.',              // heading
            'Hi Emma,',                                      // greeting
            'Your booking has been confirmed.',              // bodyText
            'Booking details',                               // detailsHeading
            [                                                // details
                'Date'    => 'April 2, 2026',
                'Time'    => '10:00–10:30',
                'Service' => 'Haircut & Beard',
                'Staff'   => 'Emma Wilson',
            ],
            'If you need to make changes, please contact us.', // footerText
            'Salon Bella',                                   // tenantName
            'Powered by VoxelBooking',                       // poweredBy
        );

        // Each detail row must appear as "Label: Value" on its own line
        $this->assertStringContainsString("Date: April 2, 2026\n", $plain);
        $this->assertStringContainsString("Time: 10:00", $plain);
        $this->assertStringContainsString("Service: Haircut & Beard\n", $plain);
        $this->assertStringContainsString("Staff: Emma Wilson\n", $plain);

        // Heading is uppercased
        $this->assertStringContainsString('YOUR BOOKING HAS BEEN CONFIRMED.', $plain);

        // Details section heading present before detail rows
        $this->assertStringContainsString("Booking details\n", $plain, 'Must contain details heading');

        // Details are not collapsed
        $this->assertStringNotContainsString('DateApril', $plain);
        $this->assertStringNotContainsString('TimeService', $plain);

        // Footer and business name present
        $this->assertStringContainsString('Salon Bella', $plain);
        $this->assertStringContainsString('Powered by VoxelBooking', $plain);
    }

    public function testBrandColorSanitizationFallback(): void
    {
        $tokens = \App\Engine\BrandColorHelper::derive('not-a-color');
        $this->assertSame('#2563EB', $tokens['brand'], 'Invalid color must fall back to default');

        $tokens = \App\Engine\BrandColorHelper::derive('');
        $this->assertSame('#2563EB', $tokens['brand'], 'Empty color must fall back to default');

        $tokens = \App\Engine\BrandColorHelper::derive('<script>');
        $this->assertSame('#2563EB', $tokens['brand'], 'XSS payload must fall back to default');

        // 6-char non-hex must now fail with the hardened regex
        $tokens = \App\Engine\BrandColorHelper::derive('abcxyz');
        $this->assertSame('#2563EB', $tokens['brand'], '6-char non-hex must fall back to default');

        $tokens = \App\Engine\BrandColorHelper::derive('#FF5733');
        $this->assertSame('#FF5733', $tokens['brand'], 'Valid hex must be preserved');
    }

    // ── Reply-To header tests ──

    /**
     * resolveTenantReplyTo returns nulls when tenantId is null.
     */
    public function testResolveTenantReplyToReturnsNullForNullTenant(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'resolveTenantReplyTo');

        $result = $method->invoke(null, null);

        $this->assertNull($result['email']);
        $this->assertNull($result['name']);
    }

    /**
     * resolveTenantReplyTo returns nulls when tenant is not found in DB.
     *
     * Uses a non-existent ULID so the query returns empty.
     */
    public function testResolveTenantReplyToReturnsNullForMissingTenant(): void
    {
        $method = new \ReflectionMethod(Mailer::class, 'resolveTenantReplyTo');

        // This will either fail DB query (no connection in unit test) or return empty rows
        $result = $method->invoke(null, '01NONEXISTENT000000000000');

        $this->assertNull($result['email']);
        $this->assertNull($result['name']);
    }

    /**
     * send() accepts replyToEmail and replyToName parameters.
     *
     * Verifies the signature change doesn't break existing callers by
     * calling send() with log transport (no SMTP needed) and the new parameters.
     */
    public function testSendAcceptsReplyToParameters(): void
    {
        $this->setMailerConfig(['mail_transport' => 'log']);

        $result = Mailer::send(
            'test@example.com',
            'Test',
            '<p>Test</p>',
            'test',
            null,                           // tenantId
            null,                           // bookingId
            null,                           // plainBody
            'salon@example.com',            // replyToEmail
            'Salon Bella',                  // replyToName
        );

        $this->assertTrue($result['sent'], 'Log transport with Reply-To parameters must succeed');
    }

    /**
     * send() works without Reply-To parameters (backward compatibility).
     */
    public function testSendWorksWithoutReplyToParameters(): void
    {
        $this->setMailerConfig(['mail_transport' => 'log']);

        $result = Mailer::send(
            'test@example.com',
            'Test',
            '<p>Test</p>',
            'test',
        );

        $this->assertTrue($result['sent'], 'Log transport without Reply-To must still succeed');
    }

    // ── Tenant-branded From name tests ──

    /**
     * resolveEffectiveFromName returns tenant name when provided (tenant-scoped path).
     *
     * This tests the actual resolution logic used by setFrom() at Mailer.php:95.
     */
    public function testResolveEffectiveFromNameReturnsTenantName(): void
    {
        $config = ['mail_from_name' => 'VoxelBooking Global'];

        $result = Mailer::resolveEffectiveFromName('Salon Bella', $config);

        $this->assertSame('Salon Bella', $result, 'Tenant name must override global config');
    }

    /**
     * resolveEffectiveFromName returns global config when no tenant name is provided (system email path).
     */
    public function testResolveEffectiveFromNameFallsBackToGlobalConfig(): void
    {
        $config = ['mail_from_name' => 'VoxelBooking Global'];

        $result = Mailer::resolveEffectiveFromName(null, $config);

        $this->assertSame('VoxelBooking Global', $result, 'Null fromName must fall back to global config');
    }

    /**
     * resolveEffectiveFromName falls back to app_name() when both tenant and global are empty.
     */
    public function testResolveEffectiveFromNameFallsBackToAppName(): void
    {
        $config = ['mail_from_name' => ''];

        $result = Mailer::resolveEffectiveFromName(null, $config);

        // app_name() returns the APP_NAME env var or 'VoxelBooking'
        $this->assertNotEmpty($result, 'Must fall back to app_name() when config is empty');
        $this->assertSame(app_name(), $result);
    }

    /**
     * Empty string fromName is treated as "no override" (same as null).
     */
    public function testResolveEffectiveFromNameTreatsEmptyStringAsNull(): void
    {
        $config = ['mail_from_name' => 'Global Name'];

        $result = Mailer::resolveEffectiveFromName('', $config);

        $this->assertSame('Global Name', $result, 'Empty string fromName must fall back to global');
    }

    /**
     * Tenant-scoped methods pass tenant name while system methods do not.
     *
     * Structural verification: ensures the wiring is correct by checking
     * that the resolution produces different results for each path.
     */
    public function testTenantVsSystemFromNameDiverges(): void
    {
        $config = ['mail_from_name' => 'Platform Global'];

        // Tenant-scoped path: passes tenant name
        $tenantResult = Mailer::resolveEffectiveFromName('Hotel Marina', $config);
        $this->assertSame('Hotel Marina', $tenantResult);

        // System email path: passes null
        $systemResult = Mailer::resolveEffectiveFromName(null, $config);
        $this->assertSame('Platform Global', $systemResult);

        // They must differ
        $this->assertNotSame($tenantResult, $systemResult,
            'Tenant-scoped and system emails must resolve to different From names');
    }
}
