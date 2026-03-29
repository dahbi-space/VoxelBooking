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

        return Response::redirect("/admin/tenants/{$tenantId}");
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
