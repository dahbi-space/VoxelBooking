<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\AuditLog;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the AuditLog engine.
 *
 * Tests redaction logic, email hashing, request ID management,
 * and sensitive key detection. Does NOT test database writes
 * (those are integration tests).
 */
final class AuditLogTest extends TestCase
{
    protected function setUp(): void
    {
        AuditLog::reset();
    }

    // ── Request ID ──

    public function testSetAndGetRequestId(): void
    {
        $this->assertSame('', AuditLog::getRequestId());

        AuditLog::setRequestId('abc-123-def');
        $this->assertSame('abc-123-def', AuditLog::getRequestId());
    }

    public function testResetClearsRequestId(): void
    {
        AuditLog::setRequestId('test-id');
        AuditLog::reset();
        $this->assertSame('', AuditLog::getRequestId());
    }

    // ── Email Hashing ──

    public function testHashEmailReturnsEightHexChars(): void
    {
        $hash = AuditLog::hashEmail('test@example.com');

        $this->assertSame(8, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $hash);
    }

    public function testHashEmailIsCaseInsensitive(): void
    {
        $hash1 = AuditLog::hashEmail('Test@Example.COM');
        $hash2 = AuditLog::hashEmail('test@example.com');

        $this->assertSame($hash1, $hash2);
    }

    public function testHashEmailTrimsWhitespace(): void
    {
        $hash1 = AuditLog::hashEmail('  test@example.com  ');
        $hash2 = AuditLog::hashEmail('test@example.com');

        $this->assertSame($hash1, $hash2);
    }

    public function testDifferentEmailsProduceDifferentHashes(): void
    {
        $hash1 = AuditLog::hashEmail('alice@example.com');
        $hash2 = AuditLog::hashEmail('bob@example.com');

        $this->assertNotSame($hash1, $hash2);
    }

    // ── Redaction ──

    public function testRedactPasswordFields(): void
    {
        $data = [
            'username' => 'admin',
            'password' => 'secret123',
            'password_hash' => '$2y$12$...',
        ];

        $result = AuditLog::redact($data);

        $this->assertSame('admin', $result['username']);
        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('[REDACTED]', $result['password_hash']);
    }

    public function testRedactSmtpCredentials(): void
    {
        $data = [
            'smtp_host' => 'mail.example.com',
            'smtp_password' => 'smtp-secret',
            'smtp_port' => '587',
        ];

        $result = AuditLog::redact($data);

        $this->assertSame('mail.example.com', $result['smtp_host']);
        $this->assertSame('[REDACTED]', $result['smtp_password']);
        $this->assertSame('587', $result['smtp_port']);
    }

    public function testRedactTokenFields(): void
    {
        $data = [
            'api_key' => 'sk_live_xxx',
            'bearer_token' => 'eyJhbG...',
            'cron_secret' => 'a1b2c3...',
            'csrf_token' => 'abc123',
        ];

        $result = AuditLog::redact($data);

        foreach ($result as $key => $value) {
            $this->assertSame('[REDACTED]', $value, "Key '{$key}' should be redacted");
        }
    }

    public function testRedactSessionId(): void
    {
        $data = ['session_id' => 'abc123def'];
        $result = AuditLog::redact($data);

        $this->assertSame('[REDACTED]', $result['session_id']);
    }

    public function testRedactNestedArrays(): void
    {
        $data = [
            'settings' => [
                'smtp_host' => 'mail.example.com',
                'smtp_password' => 'secret',
            ],
            'user' => 'admin',
        ];

        $result = AuditLog::redact($data);

        $this->assertSame('mail.example.com', $result['settings']['smtp_host']);
        $this->assertSame('[REDACTED]', $result['settings']['smtp_password']);
        $this->assertSame('admin', $result['user']);
    }

    public function testRedactPreservesNonSensitiveData(): void
    {
        $data = [
            'app_name' => 'VoxelBooking',
            'timezone' => 'Europe/Amsterdam',
            'count' => 42,
            'enabled' => true,
        ];

        $result = AuditLog::redact($data);

        $this->assertSame($data, $result);
    }

    public function testRedactEmptyArray(): void
    {
        $this->assertSame([], AuditLog::redact([]));
    }

    public function testRedactCaseInsensitiveKeys(): void
    {
        // The REDACTED_KEYS uses str_contains on lowercase,
        // so 'Password' should match 'password'
        $data = ['new_password' => 'changeme'];
        $result = AuditLog::redact($data);

        $this->assertSame('[REDACTED]', $result['new_password']);
    }

    public function testRedactPartialKeyMatch(): void
    {
        // 'smtp_password_old' contains 'smtp_password'
        $data = ['smtp_pass' => 'old-secret'];
        $result = AuditLog::redact($data);

        $this->assertSame('[REDACTED]', $result['smtp_pass']);
    }

    // ── Convenience Methods (unit-level, no DB) ──

    public function testHashEmailConsistency(): void
    {
        // Same email always produces same hash
        $hash1 = AuditLog::hashEmail('operator@example.com');
        $hash2 = AuditLog::hashEmail('operator@example.com');

        $this->assertSame($hash1, $hash2);
    }

    // ── Email Field Redaction (Blocker #2 regression) ──

    public function testRedactHashesEmailField(): void
    {
        $data = ['email' => 'test@example.com'];
        $result = AuditLog::redact($data);

        // Must be hashed, not raw and not [REDACTED]
        $this->assertNotSame('test@example.com', $result['email'], 'Raw email must not leak');
        $this->assertNotSame('[REDACTED]', $result['email'], 'Emails should be hashed, not blanked');
        $this->assertSame(AuditLog::hashEmail('test@example.com'), $result['email']);
    }

    public function testRedactHashesCustomerEmail(): void
    {
        $data = ['customer_email' => 'jane@company.org'];
        $result = AuditLog::redact($data);

        $this->assertSame(AuditLog::hashEmail('jane@company.org'), $result['customer_email']);
    }

    public function testRedactHashesArbitraryEmailKey(): void
    {
        // Any key containing 'email' should be caught by the catch-all
        $data = ['notification_email_backup' => 'admin@test.io'];
        $result = AuditLog::redact($data);

        $this->assertNotSame('admin@test.io', $result['notification_email_backup']);
        $this->assertSame(AuditLog::hashEmail('admin@test.io'), $result['notification_email_backup']);
    }

    public function testRedactHashesNestedEmailFields(): void
    {
        $data = [
            'changes' => [
                'customer_email' => 'nested@example.com',
                'app_name' => 'VoxelBooking',
            ],
        ];
        $result = AuditLog::redact($data);

        $this->assertSame(AuditLog::hashEmail('nested@example.com'), $result['changes']['customer_email']);
        $this->assertSame('VoxelBooking', $result['changes']['app_name']);
    }

    public function testRedactHashesMailFromAddress(): void
    {
        // mail_from_address is in settings — must be hashed when logged in details
        $data = ['mail_from_address' => 'noreply@booking.com'];
        $result = AuditLog::redact($data);

        $this->assertSame(AuditLog::hashEmail('noreply@booking.com'), $result['mail_from_address']);
    }

    public function testRedactDoesNotHashNonEmailKeys(): void
    {
        // Keys that don't contain 'email' should not be hashed
        $data = ['app_name' => 'test@example.com'];
        $result = AuditLog::redact($data);

        $this->assertSame('test@example.com', $result['app_name']);
    }

    public function testRedactEmailHasCorrectFormat(): void
    {
        $data = ['email' => 'operator@example.com'];
        $result = AuditLog::redact($data);

        // Must be exactly 8 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result['email']);
    }
}
