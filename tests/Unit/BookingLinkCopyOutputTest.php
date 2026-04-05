<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Output tests for the booking URL copy button.
 *
 * Asserts that all 4 admin surfaces render the copy-to-clipboard button
 * with the correct data-copy-url attribute and CSP-safe @click handler.
 */
final class BookingLinkCopyOutputTest extends TestCase
{
    private static bool $helpersLoaded = false;

    protected function setUp(): void
    {
        $basePath = dirname(__DIR__, 2);
        \App\Engine\Locale::init($basePath);

        if (!self::$helpersLoaded) {
            if (!function_exists('__')) {
                require_once $basePath . '/app/helpers.php';
            }
            self::$helpersLoaded = true;
        }

        // Minimal $_SERVER for URL generation
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'voxelbooking-app.test';
    }

    // ── Surface 1: Business Dashboard ──

    public function test_business_dashboard_has_copy_button(): void
    {
        $html = $this->renderDashboardBusiness();

        $this->assertStringContainsString(
            'data-copy-url="https://voxelbooking-app.test/book/test-salon"',
            $html,
            'Copy button must contain the full booking URL'
        );

        $this->assertStringContainsString(
            '@click="copyBookingUrl"',
            $html,
            'Copy button must use CSP-safe Alpine method reference'
        );

        $this->assertStringContainsString(
            'vb-copy-icon',
            $html,
            'Dashboard must contain copy icon element'
        );

        $this->assertStringContainsString(
            'vb-action-row',
            $html,
            'Dashboard must use bespoke action-row component'
        );
    }

    // ── Surface 2: Operator Tenant List ──

    public function test_tenant_list_has_copy_button(): void
    {
        $html = $this->renderTenantList();

        $this->assertStringContainsString(
            'data-copy-url="https://voxelbooking-app.test/book/test-salon"',
            $html,
            'Copy button must contain the full booking URL'
        );

        $this->assertStringContainsString(
            'vb-copy-icon',
            $html,
            'Tenant list must contain copy icon element'
        );

        $this->assertStringContainsString(
            'vb-table-container',
            $html,
            'Tenant list must use the new table container system'
        );

        $this->assertStringContainsString(
            'vb-table-toolbar',
            $html,
            'Tenant list must have a table toolbar'
        );
    }

    // ── Surface 3: Tenant Edit Page ──

    public function test_tenant_edit_has_copy_button(): void
    {
        $html = $this->renderTenantEdit();

        $this->assertStringContainsString(
            'vb-public-url-btn',
            $html,
            'Tenant edit page must use the public URL button component'
        );

        $this->assertStringContainsString(
            'data-copy-url="https://voxelbooking-app.test/book/test-salon"',
            $html,
            'Copy button must contain the full booking URL'
        );

        $this->assertStringContainsString(
            '@click="copyBookingUrl"',
            $html,
            'Copy button must use CSP-safe Alpine method reference'
        );

        $this->assertStringContainsString(
            'vb-public-url-card',
            $html,
            'Edit page must use the dedicated public URL card component'
        );
    }

    // ── Surface 4: General Settings ──

    public function test_general_settings_has_copy_button_and_url_display(): void
    {
        $html = $this->renderGeneralSettings();

        $this->assertStringContainsString(
            'vb-public-url-btn',
            $html,
            'General settings must contain a public URL button'
        );

        $this->assertStringContainsString(
            'slugEditor(',
            $html,
            'General settings must use the Alpine slugEditor component for live URL preview'
        );

        $this->assertStringContainsString(
            'vb-public-url-inline',
            $html,
            'Settings must use the inline public URL component inside the booking URL card'
        );

        $this->assertStringContainsString(
            'x-text="fullUrl"',
            $html,
            'URL text must use Alpine reactive binding for live preview'
        );

        $this->assertStringContainsString(
            'https://voxelbooking-app.test/book/',
            $html,
            'The base booking URL must be embedded in the slugEditor initializer'
        );
    }

    // ── Renderers ──

    private function renderDashboardBusiness(): string
    {
        $basePath = dirname(__DIR__, 2);

        $user = ['name' => 'Test Owner', 'type' => 'owner'];
        $tenant = ['id' => 'T1', 'slug' => 'test-salon', 'name' => 'Test Salon', 'brand_color' => '#2563EB', 'timezone' => 'UTC'];
        $version = '1.0.0';
        $csrfToken = 'test-csrf';
        $pageTitle = 'Dashboard';
        $activePage = 'dashboard';
        $tenantId = 'T1';
        $stats = ['active_tenants' => 1, 'bookings_today' => 0, 'bookings_week' => 0, 'bookings_upcoming_24h' => 0];
        $upcoming = [];
        $todaySchedule = [];
        $todayBookings = [];
        $weekBookings = [];
        $flash = null;

        ob_start();
        include $basePath . '/templates/admin/dashboard-business.php';
        $output = ob_get_clean() ?: '';

        return isset($content) && $content !== '' ? $content : $output;
    }

    private function renderTenantList(): string
    {
        $basePath = dirname(__DIR__, 2);

        $user = ['name' => 'Test Operator', 'type' => 'operator'];
        $version = '1.0.0';
        $csrfToken = 'test-csrf';
        $pageTitle = 'Tenants';
        $activePage = 'tenants';
        $tenants = [
            ['id' => 'T1', 'slug' => 'test-salon', 'name' => 'Test Salon', 'brand_color' => '#2563EB', 'booking_pattern' => 'timeslot', 'status' => 'active', 'booking_count' => 5, 'service_count' => 3],
        ];
        $counts = ['total' => 1];
        $flash = null;

        ob_start();
        include $basePath . '/templates/admin/tenants/index.php';
        $output = ob_get_clean() ?: '';

        return isset($content) && $content !== '' ? $content : $output;
    }

    private function renderTenantEdit(): string
    {
        $basePath = dirname(__DIR__, 2);

        $user = ['name' => 'Test Operator', 'type' => 'operator'];
        $version = '1.0.0';
        $csrfToken = 'test-csrf';
        $pageTitle = 'Edit Tenant';
        $activePage = 'tenants';
        $tenant = ['id' => 'T1', 'slug' => 'test-salon', 'name' => 'Test Salon', 'brand_color' => '#2563EB', 'booking_pattern' => 'timeslot', 'status' => 'active', 'timezone' => 'UTC', 'currency' => 'EUR', 'created_at' => '2026-01-01'];
        $flash = null;

        ob_start();
        include $basePath . '/templates/admin/tenants/edit.php';
        $output = ob_get_clean() ?: '';

        return isset($content) && $content !== '' ? $content : $output;
    }

    private function renderGeneralSettings(): string
    {
        $basePath = dirname(__DIR__, 2);

        $user = ['name' => 'Test Owner', 'type' => 'owner'];
        $version = '1.0.0';
        $csrfToken = 'test-csrf';
        $pageTitle = 'Settings';
        $activePage = 'settings';
        $activeTab = 'general';
        $tenantId = 'T1';
        $tenant = ['id' => 'T1', 'slug' => 'test-salon', 'name' => 'Test Salon', 'brand_color' => '#2563EB', 'booking_pattern' => 'timeslot', 'timezone' => 'UTC', 'currency' => 'EUR', 'email' => 'test@example.com', 'phone' => '', 'locale' => 'en'];
        $flash = null;
        $old = null;

        ob_start();
        include $basePath . '/templates/admin/tenants/settings/general.php';
        $output = ob_get_clean() ?: '';

        return isset($content) && $content !== '' ? $content : $output;
    }
}
