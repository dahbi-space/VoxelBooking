<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;
use App\Middleware\InstalledMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the PRD installation detection contract (§1453).
 *
 * This suite is split into two groups:
 *
 * **Environment-independent tests** (always run, deterministic):
 * - Tests that only manipulate $_ENV['DB_HOST'] or $_SERVER['REQUEST_URI']
 *   to verify middleware routing logic without touching a real database.
 *
 * **Database-backed tests** (require local MySQL):
 * - Tests that verify the full 4-check contract against a live database.
 * - These are skipped with a clear message when the database defined by
 *   the test environment (DB_HOST, DB_PORT, etc.) is unreachable.
 *   This happens in sandboxed CI environments without a MySQL service.
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
        Database::reset();
    }

    /**
     * Safety net: always restore installed_at after this class finishes,
     * regardless of individual test outcomes. Prevents cascading failures
     * in curl-based integration tests that depend on the app being installed.
     */
    public static function tearDownAfterClass(): void
    {
        try {
            Database::reset();
            Database::execute(
                "INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES ('installed_at', ?, NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                ['2026-03-27 10:00:00']
            );
        } catch (\Throwable) {
            // DB may be unreachable in CI — not critical
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Environment-independent tests (always run, no DB needed)
    // ═══════════════════════════════════════════════════════════════

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

    // ═══════════════════════════════════════════════════════════════
    // Database-backed tests (require live MySQL)
    //
    // Skipped with reason when the database is unreachable.
    // To run these locally: ensure MySQL is running at the host/port
    // specified in your .env (default: 127.0.0.1:3309, DB=voxelbooking).
    // ═══════════════════════════════════════════════════════════════

    public function testNotInstalledWhenDbConnectionFails(): void
    {
        // Use localhost with a port that no MySQL is listening on.
        // This fails immediately (connection refused) rather than
        // waiting for a 30-second TCP timeout like 192.0.2.1 would.
        $_ENV['DB_HOST'] = '127.0.0.1';
        $_ENV['DB_PORT'] = '1'; // port 1: no MySQL, instant refused
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

        Database::execute("DELETE FROM `settings` WHERE `key` = 'installed_at'");

        try {
            $this->assertFalse(InstalledMiddleware::isInstalled());
        } finally {
            // Always restore via UPSERT to prevent duplicate key errors
            Database::execute(
                "INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES ('installed_at', ?, NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [$hadValue ? $existing[0]['value'] : '2026-03-27 10:00:00']
            );
        }
    }

    public function testInstalledWhenAllConditionsMet(): void
    {
        $this->requireDatabase();
        $this->ensureInstalledAt();

        $this->assertTrue(InstalledMiddleware::isInstalled());
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

    // ── Helpers ──

    /**
     * Connects to the test database or skips the test.
     *
     * Tests calling this method are environment-coupled: they need
     * a MySQL server at DB_HOST:DB_PORT with the voxelbooking database
     * and settings table present. In CI environments without MySQL,
     * these tests are skipped with an explicit reason.
     */
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
            $this->markTestSkipped(
                'Database not available (environment-coupled test). '
                . 'Requires MySQL at ' . $_ENV['DB_HOST'] . ':' . $_ENV['DB_PORT']
                . '. Reason: ' . $e->getMessage()
            );
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
