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
        $this->assertStringContainsString('VoxelBooking', $html);
        $this->assertStringContainsString('<html>', $html);
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
}
