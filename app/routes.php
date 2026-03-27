<?php

declare(strict_types=1);

use App\Engine\Router;
use App\Middleware\SecurityMiddleware;
use App\Middleware\InstalledMiddleware;
use App\Middleware\ThrottleMiddleware;
use App\Middleware\CsrfMiddleware;

/**
 * All route definitions for VoxelBooking.
 *
 * Middleware pipeline order (PRD §XV Security):
 * 1. SecurityMiddleware    → HTTP headers, HTTPS redirect
 * 2. InstalledMiddleware   → Redirect to /install if not configured
 * 3. ThrottleMiddleware    → Rate limiting (before auth)
 * 4. CsrfMiddleware        → Token verification on POST/PUT/DELETE
 * 5. AuthMiddleware         → Session check (admin routes only) — Phase 2
 * 6. TenantMiddleware       → Tenant resolution (tenant routes only) — Phase 5
 */
return function (Router $router): void {

    // ── Global middleware (all routes) ──
    $router->group([
        SecurityMiddleware::class,
        InstalledMiddleware::class,
        ThrottleMiddleware::class,
        CsrfMiddleware::class,
    ], function (Router $router) {

        // ── Health check (bypasses InstalledMiddleware internally) ──
        $router->get('/health', \App\Controllers\HealthController::class, 'index');

        // ── Root redirect ──
        $router->get('/', \App\Controllers\HomeController::class, 'index');

        // ── Installation wizard ──
        // TODO: Phase 1 — /install routes

        // ── Admin ──
        // TODO: Phase 2 — /admin routes with AuthMiddleware

        // ── Public booking pages ──
        // TODO: Phase 5 — /book/{tenant-slug} routes

        // ── Public API ──
        // TODO: Phase 6 — /api/{tenant-slug} routes

        // ── Cron ──
        // TODO: Phase 6 — /cron/reminders
    });
};
