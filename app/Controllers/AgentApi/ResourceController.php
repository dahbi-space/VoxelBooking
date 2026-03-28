<?php

declare(strict_types=1);

namespace App\Controllers\AgentApi;

use App\Engine\AgentAuth;
use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;

/**
 * Agent API: Resource endpoints.
 *
 * All endpoints require Bearer token auth (enforced by AgentAuthMiddleware).
 * Data minimization: internal_notes and sensitive fields are excluded.
 * consent_given_at is returned as boolean `has_consent`.
 */
final class ResourceController
{
    /**
     * GET /api/agent/v1/tenants
     * Scope: tenants:read
     */
    public function tenants(Request $request): Response
    {
        if (!$this->hasScope($request, 'tenants:read')) {
            return $this->scopeError('tenants:read');
        }

        $tenants = Database::query(
            'SELECT `id`, `name`, `slug`, `status`, `timezone`, `locale`, `currency`, `booking_pattern`, `created_at`
             FROM `tenants`
             ORDER BY `name` ASC'
        );

        return Response::json(['data' => $tenants]);
    }

    /**
     * GET /api/agent/v1/bookings
     * Scope: bookings:read
     * Required: tenant_id query parameter
     */
    public function bookings(Request $request): Response
    {
        if (!$this->hasScope($request, 'bookings:read')) {
            return $this->scopeError('bookings:read');
        }

        $tenantId = $request->query('tenant_id');
        if (empty($tenantId)) {
            return Response::json(['error' => 'validation', 'message' => 'tenant_id is required'], 422);
        }

        $status = $request->query('status');
        $limit  = min((int) ($request->query('limit') ?: 50), 100);
        $offset = max((int) ($request->query('offset') ?: 0), 0);

        $where = ['`tenant_id` = ?'];
        $bindings = [$tenantId];

        if (!empty($status)) {
            $where[] = '`status` = ?';
            $bindings[] = $status;
        }

        $whereClause = implode(' AND ', $where);

        $total = Database::query(
            "SELECT COUNT(*) as cnt FROM `bookings` WHERE {$whereClause}",
            $bindings
        );

        $bookings = Database::query(
            "SELECT `id`, `tenant_id`, `service_id`, `staff_id`, `customer_id`,
                    `start_datetime`, `end_datetime`, `status`, `source`,
                    CASE WHEN `consent_given_at` IS NOT NULL THEN 1 ELSE 0 END as `has_consent`,
                    `created_at`
             FROM `bookings`
             WHERE {$whereClause}
             ORDER BY `start_datetime` DESC
             LIMIT ? OFFSET ?",
            array_merge($bindings, [$limit, $offset])
        );

        // Cast has_consent to boolean
        foreach ($bookings as &$b) {
            $b['has_consent'] = (bool) $b['has_consent'];
        }

        return Response::json([
            'data'  => $bookings,
            'total' => (int) ($total[0]['cnt'] ?? 0),
        ]);
    }

    /**
     * GET /api/agent/v1/services
     * Scope: services:read
     * Required: tenant_id query parameter
     */
    public function services(Request $request): Response
    {
        if (!$this->hasScope($request, 'services:read')) {
            return $this->scopeError('services:read');
        }

        $tenantId = $request->query('tenant_id');
        if (empty($tenantId)) {
            return Response::json(['error' => 'validation', 'message' => 'tenant_id is required'], 422);
        }

        $services = Database::query(
            'SELECT `id`, `name`, `description`, `duration_minutes`, `price`, `color`, `sort_order`, `is_active`
             FROM `services`
             WHERE `tenant_id` = ?
             ORDER BY `sort_order` ASC',
            [$tenantId]
        );

        // Cast is_active to boolean
        foreach ($services as &$s) {
            $s['is_active'] = (bool) $s['is_active'];
        }

        return Response::json(['data' => $services]);
    }

    /**
     * GET /api/agent/v1/availability
     * Scope: availability:read
     * Required: tenant_id query parameter
     */
    public function availability(Request $request): Response
    {
        if (!$this->hasScope($request, 'availability:read')) {
            return $this->scopeError('availability:read');
        }

        $tenantId = $request->query('tenant_id');
        if (empty($tenantId)) {
            return Response::json(['error' => 'validation', 'message' => 'tenant_id is required'], 422);
        }

        $availability = Database::query(
            'SELECT `staff_id`, `day_of_week`, `start_time`, `end_time`, `is_available`
             FROM `availability`
             WHERE `tenant_id` = ? AND `is_available` = 1
             ORDER BY `day_of_week` ASC, `start_time` ASC',
            [$tenantId]
        );

        return Response::json(['data' => $availability]);
    }

    // ── Helpers ──

    private function hasScope(Request $request, string $scope): bool
    {
        $scopes = $request->getAttribute('api_scopes') ?? [];
        return in_array($scope, $scopes, true);
    }

    private function scopeError(string $required): Response
    {
        return Response::json([
            'error'   => 'forbidden',
            'message' => "This endpoint requires the '{$required}' scope.",
        ], 403);
    }
}
