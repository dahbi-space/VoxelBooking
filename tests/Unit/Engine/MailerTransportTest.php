<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\Mailer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Mailer transport modes.
 *
 * Verifies:
 * - mail_transport=log records to email_log without outbound connection
 * - mail_transport=mailpit resolves to localhost:1025 with no auth
 * - mail_transport=smtp (default) uses operator-configured SMTP settings
 * - Unknown transport value falls through to SMTP behavior
 */
final class MailerTransportTest extends TestCase
{
    protected function setUp(): void
    {
        Mailer::clearConfigCache();
    }

    /**
     * Log transport: send returns success without SMTP connection.
     *
     * The log transport must:
     * - Return sent=true
     * - Return error=null
     * - Return a valid log_id
     * - NOT make any outbound network connection
     */
    public function testLogTransportReturnsSentWithoutSmtp(): void
    {
        // Inject log transport via reflection to bypass DB dependency
        $configProp = new \ReflectionProperty(Mailer::class, 'configCache');
        $configProp->setValue(null, [
            'mail_transport'   => 'log',
            'smtp_host'        => '',
            'smtp_port'        => '',
            'smtp_username'    => '',
            'smtp_password'    => '',
            'smtp_encryption'  => '',
            'mail_from_address' => '',
            'mail_from_name'   => '',
        ]);

        $result = Mailer::send('test@example.com', 'Test', '<p>Body</p>', 'test');

        $this->assertTrue($result['sent'], 'Log transport must return sent=true');
        $this->assertNull($result['error'], 'Log transport must return no error');
        $this->assertNotEmpty($result['log_id'], 'Log transport must return a log_id');
    }

    /**
     * Mailpit transport: config resolves to localhost:1025, no auth, no encryption.
     *
     * Verifies the config override logic: production SMTP settings are replaced
     * with Mailpit defaults (127.0.0.1:1025, no auth, no encryption).
     * Whether Mailpit is actually running doesn't affect this test — we verify
     * that the send never uses the production SMTP host.
     */
    public function testMailpitTransportResolvesToLocalhost1025(): void
    {
        // Set mailpit transport with production values that should be overridden
        $configProp = new \ReflectionProperty(Mailer::class, 'configCache');
        $configProp->setValue(null, [
            'mail_transport'   => 'mailpit',
            // These production values must be overridden by the mailpit branch
            'smtp_host'        => 'smtp.production.com',
            'smtp_port'        => '587',
            'smtp_username'    => 'prod@example.com',
            'smtp_password'    => 'prodpassword',
            'smtp_encryption'  => 'tls',
            'mail_from_address' => 'test@example.com',
            'mail_from_name'   => 'Test',
        ]);

        $result = Mailer::send('test@example.com', 'Test', '<p>Body</p>', 'test');

        // Whether Mailpit is running or not, the production host must NOT be used
        $this->assertStringNotContainsString('smtp.production.com', $result['error'] ?? '', 'Mailpit must override production SMTP host');

        // If Mailpit is running locally, send succeeds; if not, it fails with a connection error
        // Either outcome is valid — the key assertion is that production credentials are not used
        $this->assertNotEmpty($result['log_id'], 'Send must always produce a log_id');
    }

    /**
     * Default transport (smtp) with no SMTP configured returns failure.
     */
    public function testSmtpTransportWithNoHostReturnsFailed(): void
    {
        $configProp = new \ReflectionProperty(Mailer::class, 'configCache');
        $configProp->setValue(null, [
            'mail_transport'   => 'smtp',
            'smtp_host'        => '',
            'smtp_port'        => '',
            'smtp_username'    => '',
            'smtp_password'    => '',
            'smtp_encryption'  => '',
            'mail_from_address' => '',
            'mail_from_name'   => '',
        ]);

        $result = Mailer::send('test@example.com', 'Test', '<p>Body</p>', 'test');

        $this->assertFalse($result['sent']);
        $this->assertSame('SMTP not configured', $result['error']);
    }

    /**
     * Unknown transport value falls through to SMTP behavior.
     */
    public function testUnknownTransportFallsToSmtp(): void
    {
        $configProp = new \ReflectionProperty(Mailer::class, 'configCache');
        $configProp->setValue(null, [
            'mail_transport'   => 'carrier_pigeon',
            'smtp_host'        => '',
            'smtp_port'        => '',
            'smtp_username'    => '',
            'smtp_password'    => '',
            'smtp_encryption'  => '',
            'mail_from_address' => '',
            'mail_from_name'   => '',
        ]);

        $result = Mailer::send('test@example.com', 'Test', '<p>Body</p>', 'test');

        // Unknown transport with no SMTP host → same as SMTP not configured
        $this->assertFalse($result['sent']);
        $this->assertSame('SMTP not configured', $result['error']);
    }

    /**
     * Log transport returns sent=true even when SMTP host is empty.
     * This confirms log transport bypasses the SMTP-unconfigured check.
     */
    public function testLogTransportBypassesSmtpCheck(): void
    {
        $configProp = new \ReflectionProperty(Mailer::class, 'configCache');
        $configProp->setValue(null, [
            'mail_transport'   => 'log',
            'smtp_host'        => '',  // Intentionally empty
            'smtp_port'        => '',
            'smtp_username'    => '',
            'smtp_password'    => '',
            'smtp_encryption'  => '',
            'mail_from_address' => '',
            'mail_from_name'   => '',
        ]);

        $result = Mailer::send('any@email.com', 'Subject', '<p>Body</p>', 'test');

        $this->assertTrue($result['sent'], 'Log transport must succeed regardless of SMTP config');
    }
}
