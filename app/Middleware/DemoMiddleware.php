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
 * 1. Blocks all POST/PUT/DELETE requests (except allowed routes) with a 403 JSON response
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
            if ($request->isJson() || $request->method() !== 'GET') {
                return Response::json([
                    'error'   => 'demo_mode',
                    'message' => __('admin.demo.write_blocked'),
                ], 403);
            }
        }

        return $next($request);
    }
}
