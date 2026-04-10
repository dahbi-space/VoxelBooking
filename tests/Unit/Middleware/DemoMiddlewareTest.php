<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Engine\DemoMode;
use App\Engine\Request;
use App\Engine\Response;
use App\Middleware\DemoMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DemoMiddleware.
 *
 * Verifies the two-path response behavior in demo mode:
 * - /api/* routes → JSON 403 (Agent API + Booking API contract)
 * - All other routes → 302 redirect (browser form no-JS fallback)
 */
final class DemoMiddlewareTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/vb-demo-mw-' . mt_rand();
        @mkdir($this->basePath, 0755, true);
        touch($this->basePath . '/.demo');
        DemoMode::reset();
        DemoMode::init($this->basePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->basePath . '/.demo');
        @rmdir($this->basePath);
        DemoMode::reset();
    }

    private function passthrough(): callable
    {
        return fn(Request $r) => Response::html('OK', 200);
    }

    // ════════════════════════════════════════════════════════════════
    // API routes: JSON 403
    // ════════════════════════════════════════════════════════════════

    public function testApiPostReturnsJson403(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/demo-studio/bookings';

        $middleware = new DemoMiddleware();
        $response = $middleware->handle(new Request(), $this->passthrough());

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('demo_mode', $body['error']);
    }

    public function testAgentApiPostReturnsJson403(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/agent/v1/bookings';

        $middleware = new DemoMiddleware();
        $response = $middleware->handle(new Request(), $this->passthrough());

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('demo_mode', $body['error']);
    }

    public function testApiPostReturnsJson403WithoutAcceptHeader(): void
    {
        // Verify path-based classification: even without Accept: application/json,
        // /api/ routes still get JSON 403 (not a redirect).
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/demo-studio/bookings';
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);

        $middleware = new DemoMiddleware();
        $response = $middleware->handle(new Request(), $this->passthrough());

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('demo_mode', $body['error']);
    }

    // ════════════════════════════════════════════════════════════════
    // Browser form submissions: 302 redirect
    // ════════════════════════════════════════════════════════════════

    public function testPrivacyPostRedirectsBack(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/book/demo-studio/privacy/cust-id-123';

        $middleware = new DemoMiddleware();
        $response = $middleware->handle(new Request(), $this->passthrough());

        $this->assertSame(302, $response->getStatusCode());

        $ref = new \ReflectionProperty($response, 'headers');
        $headers = $ref->getValue($response);
        // Public /book/ routes redirect to same path (GET handler)
        $this->assertSame('/book/demo-studio/privacy/cust-id-123', $headers['Location'] ?? '');
    }

    public function testAdminPostRedirectsToLogin(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/tenants/t1/settings/general';

        $middleware = new DemoMiddleware();
        $response = $middleware->handle(new Request(), $this->passthrough());

        $this->assertSame(302, $response->getStatusCode());

        $ref = new \ReflectionProperty($response, 'headers');
        $headers = $ref->getValue($response);
        $this->assertSame('/admin/login', $headers['Location'] ?? '');
    }

    public function testAdminPostUsesRefererWhenAvailable(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/tenants/t1/settings/general';
        $_SERVER['HTTP_REFERER'] = 'https://demo.voxelbooking.com/admin/tenants/t1/settings/general';

        $middleware = new DemoMiddleware();
        $response = $middleware->handle(new Request(), $this->passthrough());

        $this->assertSame(302, $response->getStatusCode());

        $ref = new \ReflectionProperty($response, 'headers');
        $headers = $ref->getValue($response);
        $this->assertSame(
            'https://demo.voxelbooking.com/admin/tenants/t1/settings/general',
            $headers['Location'] ?? ''
        );

        unset($_SERVER['HTTP_REFERER']);
    }

    // ════════════════════════════════════════════════════════════════
    // Allowed routes: pass through
    // ════════════════════════════════════════════════════════════════

    public function testAllowedLoginPostPassesThrough(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/login';

        $middleware = new DemoMiddleware();
        $response = $middleware->handle(new Request(), $this->passthrough());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getBody());
    }

    public function testGetRequestsPassThrough(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin/bookings';

        $middleware = new DemoMiddleware();
        $response = $middleware->handle(new Request(), $this->passthrough());

        $this->assertSame(200, $response->getStatusCode());
    }
}
