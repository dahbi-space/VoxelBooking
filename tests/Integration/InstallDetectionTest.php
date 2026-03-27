<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;
use App\Middleware\InstalledMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the PRD installation detection contract (§1453).
 *
 * Tests all four checks:
 * 1. .env exists with non-empty DB_HOST
 * 2. DB connection succeeds
 * 3. settings table exists
 * 4. settings.installed_at is present and non-empty
 *
 * If ANY fails → show /install.
 * If ALL pass → /install returns 404.
 */
final class InstallDetectionTest extends TestCase
{
    private array $originalEnv;
    private string $originalRequestUri;

    protected function setUp(): void
    {
        $this->originalEnv = [
            'DB_HOST' => $_ENV['DB_HOST'] ?? null,
            'DB_PORT' => $_ENV['DB_PORT'] ?? null,
            'DB_DATABASE' => $_ENV['DB_DATABASE'] ?? null,
            'DB_USERNAME' => $_ENV['DB_USERNAME'] ?? null,
            'DB_PASSWORD' => $_ENV['DB_PASSWORD'] ?? null,
        ];
        $this->originalRequestUri = $_SERVER['REQUEST_URI'] ?? '/';
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        $_SERVER['REQUEST_URI'] = $this->originalRequestUri;

        // Reset DB connection so next test gets fresh state
        Database::reset();
    }

    // ── isInstalled() tests ──

    public function testNotInstalledWhenDbHostMissing(): void
    {
        unset($_ENV['DB_HOST']);

        $this->assertFalse(InstalledMiddleware::isInstalled());
    }

    public function testNotInstalledWhenDbHostEmpty(): void
    {
        $_ENV['DB_HOST'] = '';

        $this->assertFalse(InstalledMiddleware::isInstalled());
    }

    public function testNotInstalledWhenDbConnectionFails(): void
    {
        $_ENV['DB_HOST'] = '192.0.2.1'; // RFC 5737 TEST-NET: deliberately unreachable
        $_ENV['DB_PORT'] = '9999';
        $_ENV['DB_DATABASE'] = 'nonexistent';
        $_ENV['DB_USERNAME'] = 'nobody';
        $_ENV['DB_PASSWORD'] = 'wrong';
        Database::reset();

        $this->assertFalse(InstalledMiddleware::isInstalled());
    }

    public function testNotInstalledWhenInstalledAtMissing(): void
    {
        $this->requireDatabase();

        $existing = Database::query(
            "SELECT `value` FROM `settings` WHERE `key` = 'installed_at'"
        );
        $hadValue = !empty($existing) && !empty($existing[0]['value']);

        // Remove installed_at
        Database::execute("DELETE FROM `settings` WHERE `key` = 'installed_at'");

        try {
            $this->assertFalse(InstalledMiddleware::isInstalled());
        } finally {
            // Restore
            if ($hadValue) {
                Database::execute(
                    "INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES ('installed_at', ?, NOW())",
                    [$existing[0]['value']]
                );
            }
        }
    }

    public function testInstalledWhenAllConditionsMet(): void
    {
        $this->requireDatabase();
        $this->ensureInstalledAt();

        $this->assertTrue(InstalledMiddleware::isInstalled());
    }

    // ── Middleware behavior tests ──

    public function testNotInstalledNonInstallRouteRedirects(): void
    {
        unset($_ENV['DB_HOST']);
        $_SERVER['REQUEST_URI'] = '/admin';

        $middleware = new InstalledMiddleware();
        $request = new Request();
        $response = $middleware->handle($request, fn($r) => Response::html('OK', 200));

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testNotInstalledInstallRoutePasses(): void
    {
        unset($_ENV['DB_HOST']);
        $_SERVER['REQUEST_URI'] = '/install';

        $middleware = new InstalledMiddleware();
        $request = new Request();
        $response = $middleware->handle($request, fn($r) => Response::html('Install Page', 200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostInstallInstallRouteReturns404(): void
    {
        $this->requireDatabase();
        $this->ensureInstalledAt();

        $_SERVER['REQUEST_URI'] = '/install';

        $middleware = new InstalledMiddleware();
        $request = new Request();
        $response = $middleware->handle($request, fn($r) => Response::html('OK', 200));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testHealthEndpointAlwaysPasses(): void
    {
        unset($_ENV['DB_HOST']);
        $_SERVER['REQUEST_URI'] = '/health';

        $middleware = new InstalledMiddleware();
        $request = new Request();
        $response = $middleware->handle($request, fn($r) => Response::html('healthy', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('healthy', $response->getBody());
    }

    // ── Helpers ──

    private function requireDatabase(): void
    {
        $_ENV['DB_HOST'] = $this->originalEnv['DB_HOST'] ?? '127.0.0.1';
        $_ENV['DB_PORT'] = $this->originalEnv['DB_PORT'] ?? '3309';
        $_ENV['DB_DATABASE'] = $this->originalEnv['DB_DATABASE'] ?? 'voxelbooking';
        $_ENV['DB_USERNAME'] = $this->originalEnv['DB_USERNAME'] ?? 'root';
        $_ENV['DB_PASSWORD'] = $this->originalEnv['DB_PASSWORD'] ?? '';

        Database::reset();

        try {
            Database::connect();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    private function ensureInstalledAt(): void
    {
        Database::execute(
            "INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES ('installed_at', ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = ?",
            ['2026-03-27 10:00:00', '2026-03-27 10:00:00']
        );
    }
}
