<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Engine\Auth;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\View;

/**
 * Authentication middleware for admin routes.
 *
 * Per PRD §XV Security:
 * - Checks Auth::check(). If false → redirect to /admin/login
 * - Business users: restricted to /admin/tenants/{their-tenant-id}/* only
 * - Business users accessing operator-level routes → 403
 * - Business users accessing other tenants → 403
 *
 * Applied to all /admin routes except /admin/login.
 */
final class AuthMiddleware
{
    /**
     * Routes accessible only by operators (business users get 403).
     * Matched by prefix.
     */
    private const OPERATOR_ONLY_PREFIXES = [
        '/admin/settings',
        '/admin/deletion-queue',
    ];

    public function handle(Request $request, callable $next): Response
    {
        // Ensure session is started before checking auth state
        Auth::startSession();

        // Not authenticated → redirect to login
        if (!Auth::check()) {
            return Response::redirect('/admin/login');
        }

        // Operators have full access
        if (Auth::isOperator()) {
            return $next($request);
        }

        // Business user access control
        if (Auth::isBusinessUser()) {
            $path = $request->path();

            // Block operator-only routes
            foreach (self::OPERATOR_ONLY_PREFIXES as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return $this->forbidden($request);
                }
            }

            // For tenant routes, verify tenant_id matches
            $routeTenantId = $request->getAttribute('tenant_id');
            if ($routeTenantId !== null) {
                $user = Auth::user();
                if ($user === null || ($user['tenant_id'] ?? '') !== $routeTenantId) {
                    return $this->forbidden($request);
                }
            }

            // Business user on the operator dashboard → redirect to their tenant
            if ($path === '/admin' || $path === '/admin/') {
                $user = Auth::user();
                $tenantId = $user['tenant_id'] ?? '';
                if ($tenantId !== '') {
                    return Response::redirect("/admin/tenants/{$tenantId}");
                }
            }
        }

        return $next($request);
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        try {
            return View::response('admin.errors.403', [
                'user'      => Auth::user(),
                'version'   => \App\Engine\Version::get(),
                'pageTitle' => '403',
                'csrfToken' => \App\Middleware\CsrfMiddleware::generateToken(),
            ], 403);
        } catch (\Throwable) {
            return Response::html('<h1>403 Forbidden</h1><p>Access denied.</p>', 403);
        }
    }
}
