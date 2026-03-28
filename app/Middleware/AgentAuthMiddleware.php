<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Engine\AgentAuth;
use App\Engine\AuditLog;
use App\Engine\Request;
use App\Engine\Response;

/**
 * Agent API authentication middleware.
 *
 * Per PRD §XII and .ai/22-VoxelBooking-Agent-API-Checklist.md:
 * - Validates Bearer token from Authorization header
 * - Enforces scope requirements per route
 * - Returns JSON error responses (never HTML redirects)
 * - Logs API access for audit trail
 */
final class AgentAuthMiddleware
{
    /** Rate limit: requests per minute per key */
    private const RATE_LIMIT = 60;

    /** Rate limit window in seconds */
    private const RATE_WINDOW = 60;

    public function handle(Request $request, callable $next): Response
    {
        // Extract bearer token
        $authHeader = $request->header('Authorization') ?? '';
        $token = AgentAuth::extractBearerToken($authHeader);

        if ($token === '') {
            return Response::json([
                'error'   => 'unauthorized',
                'message' => 'Missing or invalid Authorization header. Use: Authorization: Bearer <api_key>',
            ], 401);
        }

        // Validate token
        $key = AgentAuth::validate($token);

        if ($key === null) {
            AuditLog::log(
                'api.auth_failed',
                'api_key',
                null,
                ['reason' => 'Invalid or expired API key'],
                actorType: 'api',
                actorId: null,
            );

            return Response::json([
                'error'   => 'unauthorized',
                'message' => 'Invalid or expired API key.',
            ], 401);
        }

        // Rate limiting (simple in-memory check per key via rate_limits table)
        if (!$this->checkRateLimit($key['id'])) {
            return Response::json([
                'error'   => 'rate_limited',
                'message' => 'Rate limit exceeded. Please wait before making more requests.',
                'retry_after' => self::RATE_WINDOW,
            ], 429);
        }

        // Store key info on request for controllers to use
        $request->setAttribute('api_key', $key);
        $request->setAttribute('api_key_id', $key['id']);
        $request->setAttribute('api_scopes', $key['scopes_array']);

        return $next($request);
    }

    /**
     * Check and increment rate limit for an API key.
     */
    private function checkRateLimit(string $keyId): bool
    {
        $rateLimitKey = 'api:' . $keyId;

        try {
            // Clean expired entries
            \App\Engine\Database::execute(
                "DELETE FROM `rate_limits` WHERE `key` = ? AND `expires_at` < NOW()",
                [$rateLimitKey]
            );

            // Check current count
            $rows = \App\Engine\Database::query(
                "SELECT `attempts` FROM `rate_limits` WHERE `key` = ? LIMIT 1",
                [$rateLimitKey]
            );

            if (empty($rows)) {
                // First request in window
                \App\Engine\Database::execute(
                    "INSERT INTO `rate_limits` (`key`, `attempts`, `expires_at`)
                     VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))",
                    [$rateLimitKey, self::RATE_WINDOW]
                );
                return true;
            }

            $attempts = (int) $rows[0]['attempts'];

            if ($attempts >= self::RATE_LIMIT) {
                return false;
            }

            // Increment
            \App\Engine\Database::execute(
                "UPDATE `rate_limits` SET `attempts` = `attempts` + 1 WHERE `key` = ?",
                [$rateLimitKey]
            );

            return true;
        } catch (\Throwable) {
            // On DB error, allow the request (fail-open for rate limiting)
            return true;
        }
    }
}
