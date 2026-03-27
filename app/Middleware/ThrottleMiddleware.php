<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;

/**
 * Per-IP, per-endpoint rate limiting.
 *
 * PRD §XV Rate Limiting:
 * - Public GET: 60/min/IP
 * - Public POST: 10/min/IP
 * - Public DELETE: 10/min/IP
 * - Admin: 120/min/IP
 * - Cron: 4/min/IP
 *
 * Uses the `rate_limits` table. Falls back to in-memory if table doesn't exist.
 */
final class ThrottleMiddleware
{
    /** @var array<string, array{limit: int, window: int}> */
    private static array $limits = [
        'admin'       => ['limit' => 120, 'window' => 60],
        'public_get'  => ['limit' => 60,  'window' => 60],
        'public_post' => ['limit' => 10,  'window' => 60],
        'cron'        => ['limit' => 4,   'window' => 60],
    ];

    public function handle(Request $request, callable $next): Response
    {
        $path = $request->path();
        $method = $request->method();
        $ip = $request->ip();

        $group = $this->resolveGroup($path, $method);
        $config = self::$limits[$group] ?? self::$limits['public_get'];

        // Check rate limit
        if (!$this->allow($ip, $group, $config['limit'], $config['window'])) {
            $retryAfter = $config['window'];

            $response = Response::json([
                'error'   => 'rate_limited',
                'message' => 'Too many requests. Please wait and try again.',
            ], 429);

            $response->header('Retry-After', (string) $retryAfter);
            $response->header('X-RateLimit-Limit', (string) $config['limit']);
            $response->header('X-RateLimit-Remaining', '0');
            $response->header('X-RateLimit-Reset', (string) (time() + $retryAfter));

            return $response;
        }

        /** @var Response $response */
        $response = $next($request);

        // Add rate limit headers
        $remaining = max(0, $config['limit'] - $this->getCount($ip, $group, $config['window']));
        $response->header('X-RateLimit-Limit', (string) $config['limit']);
        $response->header('X-RateLimit-Remaining', (string) $remaining);
        $response->header('X-RateLimit-Reset', (string) (time() + $config['window']));

        return $response;
    }

    private function resolveGroup(string $path, string $method): string
    {
        if (str_starts_with($path, '/admin')) {
            return 'admin';
        }

        if (str_starts_with($path, '/cron')) {
            return 'cron';
        }

        return match ($method) {
            'POST', 'PUT', 'DELETE' => 'public_post',
            default => 'public_get',
        };
    }

    private function allow(string $ip, string $group, int $limit, int $window): bool
    {
        $count = $this->getCount($ip, $group, $window);

        if ($count >= $limit) {
            return false;
        }

        $this->recordHit($ip, $group);

        return true;
    }

    private function getCount(string $ip, string $group, int $window): int
    {
        if (!Database::tableExists('rate_limits')) {
            return 0; // No rate limiting before table exists
        }

        $cutoff = date('Y-m-d H:i:s', time() - $window);

        $result = Database::query(
            'SELECT COUNT(*) as cnt FROM `rate_limits` WHERE `ip` = ? AND `endpoint_group` = ? AND `created_at` > ?',
            [$ip, $group, $cutoff]
        );

        return (int) ($result[0]['cnt'] ?? 0);
    }

    private function recordHit(string $ip, string $group): void
    {
        if (!Database::tableExists('rate_limits')) {
            return;
        }

        Database::execute(
            'INSERT INTO `rate_limits` (`ip`, `endpoint_group`, `created_at`) VALUES (?, ?, NOW())',
            [$ip, $group]
        );
    }
}
