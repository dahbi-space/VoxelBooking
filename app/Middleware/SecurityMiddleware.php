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
 * Per PRD §XV Security:
 * - X-Content-Type-Options: nosniff
 * - X-Frame-Options: SAMEORIGIN
 * - X-XSS-Protection: 0 (disabled, modern browsers use CSP)
 * - Referrer-Policy: strict-origin-when-cross-origin
 * - Permissions-Policy: camera=(), microphone=(), geolocation=()
 * - HSTS when FORCE_HTTPS is true
 * - HTTP → HTTPS redirect when FORCE_HTTPS is true
 */
final class SecurityMiddleware
{
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

        // Universal headers
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-XSS-Protection', '0');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS
        if ($forceHttps) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Determine context from path for CSP and caching
        $path = $request->path();

        if (str_starts_with($path, '/admin')) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
            $response->header('Content-Security-Policy',
                "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; " .
                "img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'"
            );
        } elseif (str_starts_with($path, '/api/')) {
            $response->header('Cache-Control', 'no-store');
        } elseif (str_starts_with($path, '/book/')) {
            $response->header('Cache-Control', 'public, max-age=0, must-revalidate');
            // Embed mode adjusts frame-ancestors; default blocks framing
            $response->header('Content-Security-Policy',
                "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; " .
                "img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'"
            );
        }

        return $response;
    }
}
