<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\DemoMode;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the DemoMode sentinel engine.
 */
final class DemoModeTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__, 3);
        DemoMode::reset();
        DemoMode::init($this->basePath);
    }

    protected function tearDown(): void
    {
        // Clean up sentinel file if created during tests
        $sentinel = $this->basePath . '/.demo';
        if (file_exists($sentinel)) {
            unlink($sentinel);
        }
        DemoMode::reset();
    }

    // ════════════════════════════════════════════════════════════════
    // Sentinel Detection
    // ════════════════════════════════════════════════════════════════

    public function testIsActiveReturnsFalseWhenNoSentinel(): void
    {
        // Ensure sentinel doesn't exist
        $sentinel = $this->basePath . '/.demo';
        if (file_exists($sentinel)) {
            unlink($sentinel);
        }
        DemoMode::reset();
        DemoMode::init($this->basePath);

        $this->assertFalse(DemoMode::isActive());
    }

    public function testIsActiveReturnsTrueWhenSentinelExists(): void
    {
        $sentinel = $this->basePath . '/.demo';
        touch($sentinel);
        DemoMode::reset();
        DemoMode::init($this->basePath);

        $this->assertTrue(DemoMode::isActive());
    }

    public function testIsActiveCachesResult(): void
    {
        $sentinel = $this->basePath . '/.demo';
        touch($sentinel);
        DemoMode::reset();
        DemoMode::init($this->basePath);

        $this->assertTrue(DemoMode::isActive());

        // Remove sentinel — result should still be cached
        unlink($sentinel);
        $this->assertTrue(DemoMode::isActive());

        // After reset, it should re-check
        DemoMode::reset();
        DemoMode::init($this->basePath);
        $this->assertFalse(DemoMode::isActive());
    }

    // ════════════════════════════════════════════════════════════════
    // Paths
    // ════════════════════════════════════════════════════════════════

    public function testSentinelPathReturnsCorrectLocation(): void
    {
        $expected = $this->basePath . '/.demo';
        $this->assertSame($expected, DemoMode::sentinelPath());
    }

    public function testDatabasePathReturnsCorrectLocation(): void
    {
        $expected = $this->basePath . '/storage/demo/demo.db';
        $this->assertSame($expected, DemoMode::databasePath());
    }

    // ════════════════════════════════════════════════════════════════
    // Write Allowance
    // ════════════════════════════════════════════════════════════════

    public function testGetRequestsAreAlwaysAllowed(): void
    {
        $this->assertTrue(DemoMode::isWriteAllowed('GET', '/admin/settings'));
        $this->assertTrue(DemoMode::isWriteAllowed('GET', '/admin'));
        $this->assertTrue(DemoMode::isWriteAllowed('GET', '/book/demo'));
    }

    public function testHeadRequestsAreAlwaysAllowed(): void
    {
        $this->assertTrue(DemoMode::isWriteAllowed('HEAD', '/admin/settings'));
    }

    public function testOptionsRequestsAreAlwaysAllowed(): void
    {
        $this->assertTrue(DemoMode::isWriteAllowed('OPTIONS', '/admin/settings'));
    }

    public function testLoginPostIsAllowed(): void
    {
        $this->assertTrue(DemoMode::isWriteAllowed('POST', '/admin/login'));
    }

    public function testLogoutPostIsAllowed(): void
    {
        $this->assertTrue(DemoMode::isWriteAllowed('POST', '/auth/logout'));
    }

    public function testSettingsPostIsBlocked(): void
    {
        $this->assertFalse(DemoMode::isWriteAllowed('POST', '/admin/settings'));
    }

    public function testBookingPostIsBlocked(): void
    {
        $this->assertFalse(DemoMode::isWriteAllowed('POST', '/api/demo/bookings'));
    }

    public function testDeleteRequestsAreBlocked(): void
    {
        $this->assertFalse(DemoMode::isWriteAllowed('DELETE', '/admin/deletion-queue/confirm'));
    }

    public function testPutRequestsAreBlocked(): void
    {
        $this->assertFalse(DemoMode::isWriteAllowed('PUT', '/admin/settings'));
    }

    // ════════════════════════════════════════════════════════════════
    // Reset
    // ════════════════════════════════════════════════════════════════

    public function testResetClearsCache(): void
    {
        $sentinel = $this->basePath . '/.demo';
        touch($sentinel);
        DemoMode::reset();
        DemoMode::init($this->basePath);
        $this->assertTrue(DemoMode::isActive());

        DemoMode::reset();
        DemoMode::init($this->basePath);
        unlink($sentinel);
        DemoMode::reset();
        DemoMode::init($this->basePath);
        $this->assertFalse(DemoMode::isActive());
    }
}
