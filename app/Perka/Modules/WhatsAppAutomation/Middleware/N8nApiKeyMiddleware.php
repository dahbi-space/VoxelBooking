<?php

declare(strict_types=1);

namespace App\Perka\Modules\WhatsAppAutomation\Middleware;

use App\Engine\Request;
use App\Engine\Response;

/**
 * Perka · WhatsAppAutomation · shared-secret API-key guard.
 *
 * Protects the machine-to-machine n8n endpoint (GET /api/whatsapp/profile).
 * This is NOT tenant-session auth (AuthMiddleware) and NOT the core Agent API
 * bearer-token scheme (AgentAuthMiddleware, DB-backed api_keys) — it is a
 * deliberately minimal shared secret for a single trusted external caller.
 *
 * Contract:
 *   - The caller sends `X-API-Key: <secret>`.
 *   - The secret is compared against env('N8N_API_KEY') with hash_equals()
 *     (timing-safe — no early-exit on the first differing byte).
 *   - Fail closed: if N8N_API_KEY is unset/empty, EVERY request is rejected,
 *     so a misconfigured deployment can never leave the endpoint open.
 *   - On failure: JSON 401 (never an HTML redirect), matching the Agent API
 *     middleware convention.
 *
 * Lives inside the module (not app/Middleware) so deleting app/Perka removes it
 * entirely; the core Router resolves any middleware class via `new $class()`.
 */
final class N8nApiKeyMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $expected = env('N8N_API_KEY');
        $provided = $request->header('X-API-Key') ?? '';

        // Fail closed on an unconfigured secret; still run hash_equals against a
        // non-empty provided key so the timing profile does not distinguish
        // "unconfigured" from "wrong key".
        if ($expected === '' || !hash_equals($expected, $provided)) {
            return Response::json([
                'error'   => 'unauthorized',
                'message' => 'Missing or invalid API key.',
            ], 401);
        }

        return $next($request);
    }
}
