<?php

declare(strict_types=1);

use App\Engine\Router;
use App\Middleware\SecurityMiddleware;
use App\Middleware\InstalledMiddleware;
use App\Middleware\DemoMiddleware;
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
        DemoMiddleware::class,
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
            // Audit log
            $router->get('/admin/settings/audit', \App\Controllers\Admin\SettingsController::class, 'audit');

            // Deletion queue (GDPR Art. 17 — operator review)
            $router->get('/admin/deletion-queue', \App\Controllers\Admin\DeletionQueueController::class, 'index');
            $router->post('/admin/deletion-queue/confirm', \App\Controllers\Admin\DeletionQueueController::class, 'confirm');
            $router->post('/admin/deletion-queue/dismiss', \App\Controllers\Admin\DeletionQueueController::class, 'dismiss');

            // Tenant management — operator-only enforced in controller, NOT in OPERATOR_ONLY_PREFIXES
            // (because /admin/tenants/{tenant_id}/... context routes will be business-user-accessible)
            $router->get('/admin/tenants', \App\Controllers\Admin\TenantsController::class, 'index');
            $router->get('/admin/tenants/create', \App\Controllers\Admin\TenantsController::class, 'create');
            $router->post('/admin/tenants/create', \App\Controllers\Admin\TenantsController::class, 'store');
            $router->get('/admin/tenants/{id}/edit', \App\Controllers\Admin\TenantsController::class, 'edit');
            $router->post('/admin/tenants/{id}/edit', \App\Controllers\Admin\TenantsController::class, 'update');
            $router->post('/admin/tenants/{id}/archive', \App\Controllers\Admin\TenantsController::class, 'archive');
            $router->post('/admin/tenants/{id}/activate', \App\Controllers\Admin\TenantsController::class, 'activate');

            // Booking management — operator cross-tenant routes
            $router->get('/admin/bookings', \App\Controllers\Admin\BookingsController::class, 'index');
            $router->get('/admin/bookings/{id}', \App\Controllers\Admin\BookingsController::class, 'show');
            $router->post('/admin/bookings/{id}/status', \App\Controllers\Admin\BookingsController::class, 'updateStatus');

            // Tenant context dashboard (business users land here via AuthMiddleware redirect from /admin)
            $router->get('/admin/tenants/{tenant_id}', \App\Controllers\Admin\DashboardController::class, 'tenantDashboard');

            // Booking management — tenant-context routes (business user + operator)
            $router->get('/admin/tenants/{tenant_id}/bookings', \App\Controllers\Admin\BookingsController::class, 'tenantIndex');
            $router->get('/admin/tenants/{tenant_id}/bookings/{id}', \App\Controllers\Admin\BookingsController::class, 'tenantShow');
            $router->post('/admin/tenants/{tenant_id}/bookings/{id}/status', \App\Controllers\Admin\BookingsController::class, 'tenantUpdateStatus');

            // Business user management — tenant-context (operator + owner only, enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/users', \App\Controllers\Admin\BusinessUsersController::class, 'index');
            $router->get('/admin/tenants/{tenant_id}/users/invite', \App\Controllers\Admin\BusinessUsersController::class, 'invite');
            $router->post('/admin/tenants/{tenant_id}/users/invite', \App\Controllers\Admin\BusinessUsersController::class, 'store');
            $router->post('/admin/tenants/{tenant_id}/users/{id}/deactivate', \App\Controllers\Admin\BusinessUsersController::class, 'deactivate');
            $router->post('/admin/tenants/{tenant_id}/users/{id}/activate', \App\Controllers\Admin\BusinessUsersController::class, 'activate');

            // Customer management — tenant-context (all business user roles + operator)
            $router->get('/admin/tenants/{tenant_id}/customers', \App\Controllers\Admin\CustomersController::class, 'index');
            $router->get('/admin/tenants/{tenant_id}/customers/{id}', \App\Controllers\Admin\CustomersController::class, 'show');

            // Calendar views — tenant-context (all business user roles + operator)
            $router->get('/admin/tenants/{tenant_id}/calendar', \App\Controllers\Admin\CalendarController::class, 'day');
            $router->get('/admin/tenants/{tenant_id}/calendar/week', \App\Controllers\Admin\CalendarController::class, 'week');

            // Impersonation — operator-only (enforced in controller)
            $router->post('/admin/tenants/{tenant_id}/impersonate', \App\Controllers\Admin\ImpersonationController::class, 'start');
            $router->post('/admin/impersonate/exit', \App\Controllers\Admin\ImpersonationController::class, 'exit');
        });

        // ── Public booking pages ──
        $router->get('/book/{slug}', \App\Controllers\Booking\BookingPageController::class, 'show');

        // ── Public booking API (per-tenant, no auth) ──
        $router->get('/api/{slug}/services', \App\Controllers\Booking\BookingApiController::class, 'services');
        $router->get('/api/{slug}/staff', \App\Controllers\Booking\BookingApiController::class, 'staff');
        $router->get('/api/{slug}/availability', \App\Controllers\Booking\BookingApiController::class, 'availability');
        $router->get('/api/{slug}/available-dates', \App\Controllers\Booking\BookingApiController::class, 'availableDates');
        $router->post('/api/{slug}/bookings', \App\Controllers\Booking\BookingApiController::class, 'createBooking');

        // ── Privacy endpoint (GDPR data-subject rights) ──
        // No auth — customer ULID is the bearer token (128-bit entropy)
        $router->get('/book/{slug}/privacy/{customer_id}', \App\Controllers\Booking\PrivacyController::class, 'show');
        $router->post('/book/{slug}/privacy/{customer_id}', \App\Controllers\Booking\PrivacyController::class, 'action');

        // ── Public API ──
        // TODO: Phase 6 — /api/{tenant-slug} routes

        // ── Cron ──
        $router->get('/cron/retention', \App\Controllers\CronController::class, 'retention');
        // TODO: Phase 6 — /cron/reminders

        // ── Agent API v1 ──
        // Schema endpoint: public (no auth — supports LLM tool-calling discovery)
        $router->get('/api/agent/v1/schema', \App\Controllers\AgentApi\SchemaController::class, 'index');

        // Authenticated Agent API endpoints
        $router->group([
            \App\Middleware\AgentAuthMiddleware::class,
        ], function (Router $router) {
            $router->get('/api/agent/v1/tenants', \App\Controllers\AgentApi\ResourceController::class, 'tenants');
            $router->get('/api/agent/v1/bookings', \App\Controllers\AgentApi\ResourceController::class, 'bookings');
            $router->get('/api/agent/v1/services', \App\Controllers\AgentApi\ResourceController::class, 'services');
            $router->get('/api/agent/v1/availability', \App\Controllers\AgentApi\ResourceController::class, 'availability');
        });
    });
};
