<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Engine\Auth;
use App\Engine\Request;
use App\Engine\Response;

/**
 * CSRF token verification for state-changing requests.
 *
 * Per PRD §XV Security:
 * - Per-session CSRF token on all POST/PUT/DELETE
 * - Token stored in session, verified from form field or header
 * - Booking page uses a per-page token generated server-side
 */
final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $method = $request->method();

        // Only verify on state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return $next($request);
        }

        // Skip CSRF for API routes (they use bearer token auth)
        if (str_starts_with($request->path(), '/api/')) {
            return $next($request);
        }

        // Skip CSRF for cron endpoint (uses secret token)
        if (str_starts_with($request->path(), '/cron/')) {
            return $next($request);
        }

        // Ensure session is started for CSRF verification
        // Admin routes use Auth::startSession() for consistent session naming
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (str_starts_with($request->path(), '/admin') || str_starts_with($request->path(), '/auth/')) {
                Auth::startSession();
            } else {
                session_start();
            }
        }

        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        $submittedToken = $request->string('_csrf_token')
            ?: ($request->header('X-CSRF-Token') ?? '');

        if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
            if ($request->isJson()) {
                return Response::json([
                    'error' => 'csrf_mismatch',
                    'message' => 'Invalid security token. Please refresh and try again.',
                ], 403);
            }

            return Response::html('<h1>403 Forbidden</h1><p>Invalid security token.</p>', 403);
        }

        return $next($request);
    }

    /**
     * Generate a CSRF token and store it in the session.
     */
    public static function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Prefer Auth session for admin context; caller should ensure
            // session is started before calling this in admin routes.
            session_start();
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Get the current CSRF token (for forms).
     */
    public static function token(): string
    {
        return $_SESSION['_csrf_token'] ?? '';
    }
}
