<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Engine\Request;
use App\Engine\Response;

/**
 * Adds HTTP security headers to every response.
 *
 * This middleware fires FIRST in the pipeline so that every response
 * (including error responses from downstream middleware) carries security headers.
 *
 * Per PRD §XV Security — headers are applied unconditionally.
 * Context-specific headers (CSP, Cache-Control) vary by path prefix.
 * Routes not matching any prefix still get the default CSP.
 */
final class SecurityMiddleware
{
    private const DEFAULT_CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'";

    public function handle(Request $request, callable $next): Response
    {
        $forceHttps = ($_ENV['FORCE_HTTPS'] ?? 'false') === 'true';

        // HTTPS redirect
        if ($forceHttps && !$request->isSecure()) {
            $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $request->path();

            return Response::redirect($url, 301);
        }

        /** @var Response $response */
        $response = $next($request);

        // Universal security headers — present on EVERY response
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-XSS-Protection', '0');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS
        if ($forceHttps) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP — always present, context-specific policy
        $path = $request->path();

        if (str_starts_with($path, '/install') || str_starts_with($path, '/admin')) {
            // Install wizard + admin: inline <script> + <style> blocks for theme resolution
            // All assets self-hosted — no external CDN
            $response->header('Content-Security-Policy',
                "default-src 'self'; script-src 'self' 'unsafe-inline'; "
                . "style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data:; font-src 'self'; "
                . "connect-src 'self'; frame-ancestors 'none'"
            );
        } else {
            $response->header('Content-Security-Policy', self::DEFAULT_CSP);
        }

        // Context-specific cache control
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/install')) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
        } elseif (str_starts_with($path, '/api/')) {
            $response->header('Cache-Control', 'no-store');
        } elseif (str_starts_with($path, '/book/')) {
            $response->header('Cache-Control', 'public, max-age=0, must-revalidate');
        } else {
            // Default: no caching for unmatched routes (health, install, etc.)
            $response->header('Cache-Control', 'no-store');
        }

        return $response;
    }
}
