<?php

declare(strict_types=1);

use App\Engine\Router;
use App\Middleware\SecurityMiddleware;
use App\Middleware\InstalledMiddleware;
use App\Middleware\ThrottleMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthMiddleware;

/**
 * All route definitions for VoxelBooking.
 *
 * Middleware pipeline order (PRD §XV Security):
 * 1. SecurityMiddleware    → HTTP headers, HTTPS redirect
 * 2. InstalledMiddleware   → Redirect to /install if not configured
 * 3. ThrottleMiddleware    → Rate limiting (before auth)
 * 4. CsrfMiddleware        → Token verification on POST/PUT/DELETE
 * 5. AuthMiddleware         → Session check (admin routes only)
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

        // ── Health check ──
        $router->get('/health', \App\Controllers\HealthController::class, 'index');

        // ── Root redirect ──
        $router->get('/', \App\Controllers\HomeController::class, 'index');

        // ── Installation wizard ──
        $router->get('/install', \App\Controllers\Install\WizardController::class, 'show');
        $router->post('/install/step/2', \App\Controllers\Install\WizardController::class, 'stepTwo');
        $router->post('/install/step/3', \App\Controllers\Install\WizardController::class, 'stepThree');
        $router->post('/install/step/4', \App\Controllers\Install\WizardController::class, 'stepFour');
        $router->post('/install/step/5', \App\Controllers\Install\WizardController::class, 'stepFive');
        $router->post('/install/complete', \App\Controllers\Install\WizardController::class, 'complete');

        // ── Auth (no AuthMiddleware — login page must be accessible) ──
        $router->get('/admin/login', \App\Controllers\Auth\AuthController::class, 'showLogin');
        $router->post('/admin/login', \App\Controllers\Auth\AuthController::class, 'login');
        $router->post('/auth/logout', \App\Controllers\Auth\AuthController::class, 'logout');

        // ── Admin (protected by AuthMiddleware) ──
        $router->group([
            AuthMiddleware::class,
        ], function (Router $router) {

            // Dashboard
            $router->get('/admin', \App\Controllers\Admin\DashboardController::class, 'index');

            // Settings (operator-only — AuthMiddleware enforces this)
            $router->get('/admin/settings', \App\Controllers\Admin\SettingsController::class, 'general');
            $router->post('/admin/settings', \App\Controllers\Admin\SettingsController::class, 'saveGeneral');
            $router->get('/admin/settings/account', \App\Controllers\Admin\SettingsController::class, 'account');
            $router->post('/admin/settings/account', \App\Controllers\Admin\SettingsController::class, 'saveAccount');
            $router->get('/admin/settings/email', \App\Controllers\Admin\SettingsController::class, 'email');
            $router->post('/admin/settings/email', \App\Controllers\Admin\SettingsController::class, 'saveEmail');
            $router->get('/admin/settings/cron', \App\Controllers\Admin\SettingsController::class, 'cron');
            $router->get('/admin/settings/logs', \App\Controllers\Admin\SettingsController::class, 'logs');

            // TODO: Phase 3 — Tenant management routes
        });

        // ── Public booking pages ──
        // TODO: Phase 5 — /book/{tenant-slug} routes

        // ── Public API ──
        // TODO: Phase 6 — /api/{tenant-slug} routes

        // ── Cron ──
        // TODO: Phase 6 — /cron/reminders
    });
};
