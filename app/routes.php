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

        // ── Passwordless auth (OTP + magic link) ──
        $router->post('/admin/login/request-code', \App\Controllers\Auth\AuthController::class, 'requestCode');
        $router->get('/admin/login/verify-code', \App\Controllers\Auth\AuthController::class, 'showVerifyCode');
        $router->post('/admin/login/verify-code', \App\Controllers\Auth\AuthController::class, 'verifyCode');
        $router->get('/admin/login/verify', \App\Controllers\Auth\AuthController::class, 'verifyMagicLink');

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
            $router->post('/admin/settings/cron/run', \App\Controllers\Admin\SettingsController::class, 'cronRunNow');
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
            $router->get('/admin/tenants/{tenant_id}/bookings/create', \App\Controllers\Admin\BookingsController::class, 'tenantCreate');
            $router->post('/admin/tenants/{tenant_id}/bookings/create', \App\Controllers\Admin\BookingsController::class, 'tenantStore');
            $router->get('/admin/tenants/{tenant_id}/bookings/{id}', \App\Controllers\Admin\BookingsController::class, 'tenantShow');
            $router->post('/admin/tenants/{tenant_id}/bookings/{id}/status', \App\Controllers\Admin\BookingsController::class, 'tenantUpdateStatus');

            // Business user management — tenant-context (operator + owner only, enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/users', \App\Controllers\Admin\BusinessUsersController::class, 'index');
            $router->get('/admin/tenants/{tenant_id}/users/invite', \App\Controllers\Admin\BusinessUsersController::class, 'invite');
            $router->post('/admin/tenants/{tenant_id}/users/invite', \App\Controllers\Admin\BusinessUsersController::class, 'store');
            $router->post('/admin/tenants/{tenant_id}/users/{id}/deactivate', \App\Controllers\Admin\BusinessUsersController::class, 'deactivate');
            $router->post('/admin/tenants/{tenant_id}/users/{id}/activate', \App\Controllers\Admin\BusinessUsersController::class, 'activate');

            // Tenant settings — operator + owner only (enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/settings', \App\Controllers\Admin\TenantSettingsController::class, 'general');
            $router->post('/admin/tenants/{tenant_id}/settings', \App\Controllers\Admin\TenantSettingsController::class, 'saveGeneral');
            $router->get('/admin/tenants/{tenant_id}/settings/branding', \App\Controllers\Admin\TenantSettingsController::class, 'branding');
            $router->post('/admin/tenants/{tenant_id}/settings/branding', \App\Controllers\Admin\TenantSettingsController::class, 'saveBranding');
            $router->get('/admin/tenants/{tenant_id}/settings/bookingpage', \App\Controllers\Admin\TenantSettingsController::class, 'bookingPage');
            $router->post('/admin/tenants/{tenant_id}/settings/bookingpage', \App\Controllers\Admin\TenantSettingsController::class, 'saveBookingPage');
            $router->get('/admin/tenants/{tenant_id}/settings/booking', \App\Controllers\Admin\TenantSettingsController::class, 'booking');
            $router->post('/admin/tenants/{tenant_id}/settings/booking', \App\Controllers\Admin\TenantSettingsController::class, 'saveBooking');
            $router->get('/admin/tenants/{tenant_id}/settings/privacy', \App\Controllers\Admin\TenantSettingsController::class, 'privacy');
            $router->post('/admin/tenants/{tenant_id}/settings/privacy', \App\Controllers\Admin\TenantSettingsController::class, 'savePrivacy');
            $router->get('/admin/tenants/{tenant_id}/settings/notifications', \App\Controllers\Admin\TenantSettingsController::class, 'notifications');
            $router->post('/admin/tenants/{tenant_id}/settings/notifications', \App\Controllers\Admin\TenantSettingsController::class, 'saveNotifications');
            $router->get('/admin/tenants/{tenant_id}/settings/emails', \App\Controllers\Admin\TenantSettingsController::class, 'emails');
            $router->post('/admin/tenants/{tenant_id}/settings/emails', \App\Controllers\Admin\TenantSettingsController::class, 'saveEmails');

            // Customer management — tenant-context (all business user roles + operator)
            $router->get('/admin/tenants/{tenant_id}/customers', \App\Controllers\Admin\CustomersController::class, 'index');
            $router->get('/admin/tenants/{tenant_id}/customers/{id}', \App\Controllers\Admin\CustomersController::class, 'show');

            // Service management — operator + owner only (enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/services', \App\Controllers\Admin\ServiceController::class, 'index');
            $router->get('/admin/tenants/{tenant_id}/services/create', \App\Controllers\Admin\ServiceController::class, 'create');
            $router->post('/admin/tenants/{tenant_id}/services', \App\Controllers\Admin\ServiceController::class, 'store');
            $router->get('/admin/tenants/{tenant_id}/services/{id}/edit', \App\Controllers\Admin\ServiceController::class, 'edit');
            $router->post('/admin/tenants/{tenant_id}/services/{id}', \App\Controllers\Admin\ServiceController::class, 'update');
            $router->post('/admin/tenants/{tenant_id}/services/{id}/activate', \App\Controllers\Admin\ServiceController::class, 'activate');
            $router->post('/admin/tenants/{tenant_id}/services/{id}/deactivate', \App\Controllers\Admin\ServiceController::class, 'deactivate');

            // Staff management — operator + owner only (enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/staff', \App\Controllers\Admin\StaffController::class, 'index');
            $router->get('/admin/tenants/{tenant_id}/staff/create', \App\Controllers\Admin\StaffController::class, 'create');
            $router->post('/admin/tenants/{tenant_id}/staff/create', \App\Controllers\Admin\StaffController::class, 'store');
            $router->get('/admin/tenants/{tenant_id}/staff/{id}/edit', \App\Controllers\Admin\StaffController::class, 'edit');
            $router->post('/admin/tenants/{tenant_id}/staff/{id}/edit', \App\Controllers\Admin\StaffController::class, 'update');
            $router->post('/admin/tenants/{tenant_id}/staff/{id}/activate', \App\Controllers\Admin\StaffController::class, 'activate');
            $router->post('/admin/tenants/{tenant_id}/staff/{id}/deactivate', \App\Controllers\Admin\StaffController::class, 'deactivate');

            // Availability management — operator + owner only (enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/availability', \App\Controllers\Admin\AvailabilityController::class, 'index');
            $router->post('/admin/tenants/{tenant_id}/availability', \App\Controllers\Admin\AvailabilityController::class, 'save');
            $router->get('/admin/tenants/{tenant_id}/availability/staff/{id}', \App\Controllers\Admin\AvailabilityController::class, 'staffOverride');
            $router->post('/admin/tenants/{tenant_id}/availability/staff/{id}', \App\Controllers\Admin\AvailabilityController::class, 'saveStaffOverride');
            $router->post('/admin/tenants/{tenant_id}/availability/staff/{id}/reset', \App\Controllers\Admin\AvailabilityController::class, 'resetStaffOverride');

            // Blocked dates — operator + owner only (enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/blocked-dates', \App\Controllers\Admin\BlockedDatesController::class, 'index');
            $router->post('/admin/tenants/{tenant_id}/blocked-dates', \App\Controllers\Admin\BlockedDatesController::class, 'store');
            $router->post('/admin/tenants/{tenant_id}/blocked-dates/{id}/delete', \App\Controllers\Admin\BlockedDatesController::class, 'delete');

            // Resource management — operator + owner only (enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/resources', \App\Controllers\Admin\ResourceController::class, 'index');
            $router->get('/admin/tenants/{tenant_id}/resources/create', \App\Controllers\Admin\ResourceController::class, 'create');
            $router->post('/admin/tenants/{tenant_id}/resources', \App\Controllers\Admin\ResourceController::class, 'store');
            $router->get('/admin/tenants/{tenant_id}/resources/{id}/edit', \App\Controllers\Admin\ResourceController::class, 'edit');
            $router->post('/admin/tenants/{tenant_id}/resources/{id}', \App\Controllers\Admin\ResourceController::class, 'update');
            $router->post('/admin/tenants/{tenant_id}/resources/{id}/activate', \App\Controllers\Admin\ResourceController::class, 'activate');
            $router->post('/admin/tenants/{tenant_id}/resources/{id}/deactivate', \App\Controllers\Admin\ResourceController::class, 'deactivate');

            // Capacity slot management — operator + owner only (enforced in controller)
            $router->get('/admin/tenants/{tenant_id}/capacity-slots', \App\Controllers\Admin\CapacitySlotsController::class, 'index');
            $router->post('/admin/tenants/{tenant_id}/capacity-slots', \App\Controllers\Admin\CapacitySlotsController::class, 'store');
            $router->post('/admin/tenants/{tenant_id}/capacity-slots/{id}/toggle', \App\Controllers\Admin\CapacitySlotsController::class, 'toggleActive');
            $router->post('/admin/tenants/{tenant_id}/capacity-slots/{id}/delete', \App\Controllers\Admin\CapacitySlotsController::class, 'delete');

            // Events management (Phase E — event-pattern tenants)
            $router->get('/admin/tenants/{tenant_id}/events', \App\Controllers\Admin\EventsController::class, 'index');
            $router->get('/admin/tenants/{tenant_id}/events/create', \App\Controllers\Admin\EventsController::class, 'create');
            $router->post('/admin/tenants/{tenant_id}/events', \App\Controllers\Admin\EventsController::class, 'store');
            $router->get('/admin/tenants/{tenant_id}/events/{id}/edit', \App\Controllers\Admin\EventsController::class, 'edit');
            $router->post('/admin/tenants/{tenant_id}/events/{id}', \App\Controllers\Admin\EventsController::class, 'update');
            $router->post('/admin/tenants/{tenant_id}/events/{id}/toggle', \App\Controllers\Admin\EventsController::class, 'toggleActive');
            $router->post('/admin/tenants/{tenant_id}/events/{id}/delete', \App\Controllers\Admin\EventsController::class, 'delete');

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

        // Resource-pattern public API (Phase R)
        $router->get('/api/{slug}/resources', \App\Controllers\Booking\BookingApiController::class, 'resources');
        $router->get('/api/{slug}/resources/{id}/availability', \App\Controllers\Booking\BookingApiController::class, 'resourceAvailability');

        // Capacity-pattern public API (Phase C)
        $router->get('/api/{slug}/capacity/available-dates', \App\Controllers\Booking\BookingApiController::class, 'capacityAvailableDates');
        $router->get('/api/{slug}/capacity/slots', \App\Controllers\Booking\BookingApiController::class, 'capacitySlots');

        // Event-pattern public API (Phase E)
        $router->get('/api/{slug}/events', \App\Controllers\Booking\BookingApiController::class, 'events');
        $router->get('/api/{slug}/events/{id}', \App\Controllers\Booking\BookingApiController::class, 'eventDetail');

        // ── Privacy endpoint (GDPR data-subject rights) ──
        // No auth — customer ULID is the bearer token (128-bit entropy)
        $router->get('/book/{slug}/privacy/{customer_id}', \App\Controllers\Booking\PrivacyController::class, 'show');
        $router->post('/book/{slug}/privacy/{customer_id}', \App\Controllers\Booking\PrivacyController::class, 'action');

        // ── Self-service booking management ──
        // No auth — booking ULID is the bearer token (128-bit entropy, same as privacy page)
        $router->get('/book/{slug}/manage/{booking_id}', \App\Controllers\Booking\BookingPageController::class, 'manage');
        $router->get('/api/{slug}/bookings/{id}', \App\Controllers\Booking\BookingApiController::class, 'bookingDetail');
        $router->post('/api/{slug}/bookings/{id}/cancel', \App\Controllers\Booking\BookingApiController::class, 'cancelBookingAction');

        // ── Cron ──
        $router->get('/cron/run', \App\Controllers\CronController::class, 'run');
        $router->get('/cron/retention', \App\Controllers\CronController::class, 'run'); // backward compat

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
