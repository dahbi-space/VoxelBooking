<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;

/**
 * Operator tenant impersonation controller.
 *
 * POST /admin/tenants/{tenant_id}/impersonate → start impersonation
 * POST /admin/impersonate/exit               → end impersonation
 *
 * Only operators can impersonate. No existing business_user row required.
 * The operator's real identity is preserved as the session actor.
 */
final class ImpersonationController
{
    /**
     * Start impersonating a tenant.
     *
     * Requires: authenticated operator, valid tenant_id.
     * Result: impersonation session keys set, redirect to tenant dashboard.
     */
    public function start(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return Response::redirect('/admin');
        }

        $tenantId = $request->getAttribute('tenant_id');

        // Verify tenant exists
        $rows = Database::query(
            'SELECT `id`, `name` FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );

        if (empty($rows)) {
            return Response::redirect('/admin/tenants');
        }

        $tenant = $rows[0];

        Auth::startImpersonation($tenantId, $tenant['name']);

        // Support deep-link redirect after impersonation start.
        // Canonical path validation — rejects:
        //   • dot-segments (../)
        //   • URL-encoded traversal (%2e, %2f)
        //   • scheme/host injection (http://, //evil.com)
        //   • prefix-boundary ambiguity (/admin/tenants/{id}-evil)
        $redirectTo = $request->string('redirect_to');
        $allowedPrefix = "/admin/tenants/{$tenantId}";

        if ($redirectTo !== '' && self::isSafeRedirect($redirectTo, $allowedPrefix)) {
            return Response::redirect($redirectTo);
        }

        return Response::redirect($allowedPrefix);
    }

    /**
     * Validate that a redirect path is safe — owned by the tenant prefix.
     *
     * Rules:
     * 1. Must be a relative path (no scheme, no host, no authority)
     * 2. Must not contain dot-segments (..)
     * 3. Must not contain percent-encoded characters (prevents %2e%2e bypass)
     * 4. Must exactly equal the prefix OR start with prefix + '/'
     */
    private static function isSafeRedirect(string $path, string $prefix): bool
    {
        // Reject scheme/host (parse_url detects //host and scheme://host)
        $parsed = parse_url($path);
        if (isset($parsed['scheme']) || isset($parsed['host'])) {
            return false;
        }

        // Work with only the path component (strip query/fragment)
        $cleanPath = $parsed['path'] ?? '';
        if ($cleanPath === '') {
            return false;
        }

        // Reject dot-segments and encoded traversal
        if (str_contains($cleanPath, '..') || str_contains($cleanPath, '%')) {
            return false;
        }

        // Exact match or prefix + '/' boundary (no prefix-boundary tricks)
        return $cleanPath === $prefix || str_starts_with($cleanPath, $prefix . '/');
    }

    /**
     * End impersonation and return to operator context.
     */
    public function exit(Request $request): Response
    {
        if (Auth::isImpersonating()) {
            Auth::endImpersonation();
        }

        return Response::redirect('/admin/tenants');
    }
}
