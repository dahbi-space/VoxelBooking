<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Engine\DemoMode;
use App\Engine\Request;
use App\Engine\Response;

/**
 * Demo mode middleware.
 *
 * When demo mode is active (.demo sentinel exists):
 * 1. Blocks all POST/PUT/DELETE requests (except allowed routes):
 *    - /api/* routes: returns 403 JSON (Agent API + Booking API contract)
 *    - All other routes: returns 302 redirect back (no-JS browser forms)
 * 2. Injects `window.VB_DEMO = true` flag for frontend guards
 *
 * This middleware should run AFTER SecurityMiddleware and InstalledMiddleware,
 * but BEFORE CsrfMiddleware (blocked requests don't need CSRF validation).
 */
final class DemoMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!DemoMode::isActive()) {
            return $next($request);
        }

        // Block non-allowed write requests
        if (!DemoMode::isWriteAllowed($request->method(), $request->path())) {
            // API routes: always JSON 403 (Agent API, Booking API — no header dependency)
            if (str_starts_with($request->path(), '/api/')) {
                return Response::json([
                    'error'   => 'demo_mode',
                    'message' => __('admin.demo.write_blocked'),
                ], 403);
            }

            // Browser form submissions (admin/public no-JS fallback): redirect back
            $referer = $request->header('Referer');
            $fallback = str_starts_with($request->path(), '/book/')
                ? $request->path()   // Public pages: redirect to same path (GET handler)
                : '/admin/login';    // Admin pages: redirect to login
            return Response::redirect($referer ?: $fallback);
        }

        return $next($request);
    }
}
