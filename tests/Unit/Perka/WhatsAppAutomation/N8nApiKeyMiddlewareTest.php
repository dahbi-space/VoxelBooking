<?php

declare(strict_types=1);

namespace Tests\Unit\Perka\WhatsAppAutomation;

use App\Engine\Request;
use App\Engine\Response;
use App\Perka\Modules\WhatsAppAutomation\Middleware\N8nApiKeyMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the WhatsAppAutomation shared-secret guard.
 *
 * Fully self-contained — no database or web server. Exercises the
 * X-API-Key vs env(N8N_API_KEY) comparison, including the fail-closed
 * behaviour when the secret is unconfigured.
 */
final class N8nApiKeyMiddlewareTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $envBackup = [];
    private ?string $headerBackup = null;

    protected function setUp(): void
    {
        $this->envBackup    = ['set' => array_key_exists('N8N_API_KEY', $_ENV), 'val' => $_ENV['N8N_API_KEY'] ?? null];
        $this->headerBackup = $_SERVER['HTTP_X_API_KEY'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->envBackup['set']) {
            $_ENV['N8N_API_KEY'] = $this->envBackup['val'];
        } else {
            unset($_ENV['N8N_API_KEY']);
        }

        if ($this->headerBackup === null) {
            unset($_SERVER['HTTP_X_API_KEY']);
        } else {
            $_SERVER['HTTP_X_API_KEY'] = $this->headerBackup;
        }

        // env() also consults $_SERVER as a fallback — keep it clean.
        unset($_SERVER['N8N_API_KEY']);
    }

    private function dispatch(): Response
    {
        $middleware = new N8nApiKeyMiddleware();
        $request = new Request();

        return $middleware->handle($request, fn ($r) => Response::json(['ok' => true], 200));
    }

    public function testValidKeyPassesThrough(): void
    {
        $_ENV['N8N_API_KEY'] = 'super-secret-value';
        $_SERVER['HTTP_X_API_KEY'] = 'super-secret-value';

        $response = $this->dispatch();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"ok":true', $response->getBody());
    }

    public function testWrongKeyIsRejectedWith401(): void
    {
        $_ENV['N8N_API_KEY'] = 'super-secret-value';
        $_SERVER['HTTP_X_API_KEY'] = 'not-the-key';

        $response = $this->dispatch();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('unauthorized', $response->getBody());
    }

    public function testMissingHeaderIsRejectedWith401(): void
    {
        $_ENV['N8N_API_KEY'] = 'super-secret-value';
        unset($_SERVER['HTTP_X_API_KEY']);

        $response = $this->dispatch();

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testUnconfiguredSecretFailsClosed(): void
    {
        // No N8N_API_KEY set anywhere, and the caller even sends an empty key.
        unset($_ENV['N8N_API_KEY'], $_SERVER['N8N_API_KEY']);
        $_SERVER['HTTP_X_API_KEY'] = '';

        $response = $this->dispatch();

        $this->assertSame(401, $response->getStatusCode(),
            'A blank/unset secret must reject every request (fail closed).');
    }

    public function testUnconfiguredSecretRejectsEvenNonEmptyKey(): void
    {
        unset($_ENV['N8N_API_KEY'], $_SERVER['N8N_API_KEY']);
        $_SERVER['HTTP_X_API_KEY'] = 'anything';

        $response = $this->dispatch();

        $this->assertSame(401, $response->getStatusCode());
    }
}
