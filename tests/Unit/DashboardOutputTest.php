<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Template output tests for dashboard pages.
 *
 * Renders dashboard templates with mock data by extracting the $content
 * variable that they produce via ob_start()/ob_get_clean().
 *
 * The templates end with: $content = ob_get_clean(); include layout.php;
 * We intercept this by wrapping the include in output buffering and
 * discarding the layout output, then reading $content.
 *
 * No HTTP server required.
 */
final class DashboardOutputTest extends TestCase
{
    private string $templateDir;
    private static bool $helpersReady = false;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/vendor/autoload.php';

        if (file_exists($root . '/.env')) {
            \App\Engine\EnvLoader::load($root . '/.env');
        }

        self::$helpersReady = function_exists('__') && function_exists('app_name');
    }

    protected function setUp(): void
    {
        if (!self::$helpersReady) {
            $this->markTestSkipped('Translation/app helpers not available');
        }

        $this->templateDir = dirname(__DIR__, 2) . '/templates';
    }

    // ════════════════════════════════════════════════════════════════
    // Operator dashboard
    // ════════════════════════════════════════════════════════════════

    public function test_operator_dashboard_renders_metric_delta_up(): void
    {
        $html = $this->renderOperatorDashboard([
            'deltaToday' => 5,
            'deltaWeek' => 3,
            'todayBookings' => 10,
            'weekBookings' => 25,
        ]);

        $this->assertStringContainsString('vb-metric-delta', $html);
        $this->assertStringContainsString('is-up', $html);
        $this->assertStringContainsString('trending-up', $html);
    }

    public function test_operator_dashboard_renders_metric_delta_down(): void
    {
        $html = $this->renderOperatorDashboard([
            'deltaToday' => -3,
            'deltaWeek' => -2,
            'todayBookings' => 5,
            'weekBookings' => 10,
        ]);

        $this->assertStringContainsString('is-down', $html);
        $this->assertStringContainsString('trending-down', $html);
    }

    public function test_operator_dashboard_hides_delta_when_zero(): void
    {
        $html = $this->renderOperatorDashboard([
            'deltaToday' => 0,
            'deltaWeek' => 0,
            'todayBookings' => 5,
            'weekBookings' => 10,
        ]);

        $this->assertStringNotContainsString('vb-metric-delta', $html);
    }

    public function test_operator_dashboard_renders_upcoming_list(): void
    {
        $html = $this->renderOperatorDashboard([
            'upcoming' => [
                [
                    'customer_name' => 'Jane Doe',
                    'customer_email' => 'jane@example.com',
                    'service_name' => 'Haircut',
                    'start_datetime' => date('Y-m-d 10:00:00', strtotime('+1 day')),
                    'end_datetime' => date('Y-m-d 10:30:00', strtotime('+1 day')),
                    'status' => 'confirmed',
                    'tenant_name' => 'Test Salon',
                ],
            ],
        ]);

        $this->assertStringContainsString('vb-status', $html);
        $this->assertStringContainsString('vb-status-confirmed', $html);
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('Test Salon', $html);
        $this->assertStringContainsString('vb-cell-primary', $html);
    }

    public function test_operator_dashboard_renders_metric_headers(): void
    {
        $html = $this->renderOperatorDashboard();

        $this->assertStringContainsString('vb-metric-header', $html);
        $this->assertStringContainsString('vb-metric-icon', $html);
        $this->assertStringContainsString('vb-metric-value-row', $html);
    }

    // ════════════════════════════════════════════════════════════════
    // Tenant dashboard
    // ════════════════════════════════════════════════════════════════

    public function test_tenant_dashboard_renders_schedule_strip(): void
    {
        $html = $this->renderTenantDashboard([
            'todaySchedule' => [
                [
                    'id' => 'test-id',
                    'start_datetime' => date('Y-m-d 09:00:00'),
                    'end_datetime' => date('Y-m-d 09:30:00'),
                    'status' => 'confirmed',
                    'booking_pattern' => 'timeslot',
                    'staff_id' => null,
                    'customer_name' => 'Test Customer',
                    'customer_email' => 'test@example.com',
                    'service_name' => 'Massage',
                    'service_color' => null,
                    'staff_name' => null,
                ],
            ],
        ]);

        $this->assertStringContainsString('vb-schedule-grid', $html);
        $this->assertStringContainsString('vb-schedule-pill', $html);
        $this->assertStringContainsString('Test Customer', $html);
    }

    public function test_tenant_dashboard_renders_service_color_pills(): void
    {
        $html = $this->renderTenantDashboard([
            'todaySchedule' => [
                [
                    'id' => 'test-id',
                    'start_datetime' => date('Y-m-d 10:00:00'),
                    'end_datetime' => date('Y-m-d 10:30:00'),
                    'status' => 'confirmed',
                    'booking_pattern' => 'timeslot',
                    'staff_id' => null,
                    'customer_name' => 'Color Test',
                    'customer_email' => 'color@test.com',
                    'service_name' => 'Styled Service',
                    'service_color' => '#3B82F6',
                    'staff_name' => null,
                ],
            ],
        ]);

        $this->assertStringContainsString('rgba(59, 130, 246, 0.12)', $html);
        $this->assertStringContainsString('#3B82F6', $html);
    }

    public function test_tenant_dashboard_renders_accent_fallback_pills(): void
    {
        $html = $this->renderTenantDashboard([
            'todaySchedule' => [
                [
                    'id' => 'test-id',
                    'start_datetime' => date('Y-m-d 11:00:00'),
                    'end_datetime' => date('Y-m-d 11:30:00'),
                    'status' => 'confirmed',
                    'booking_pattern' => 'timeslot',
                    'staff_id' => null,
                    'customer_name' => 'Fallback Test',
                    'customer_email' => 'fallback@test.com',
                    'service_name' => 'No Color Service',
                    'service_color' => null,
                    'staff_name' => null,
                ],
            ],
        ]);

        $this->assertStringContainsString('var(--vb-accent-subtle)', $html);
        $this->assertStringContainsString('var(--vb-accent)', $html);
    }

    public function test_tenant_dashboard_renders_schedule_empty_state(): void
    {
        $html = $this->renderTenantDashboard([
            'todaySchedule' => [],
        ]);

        $this->assertStringNotContainsString('vb-schedule-grid', $html);
        $this->assertStringContainsString('calendar-off', $html);
    }

    public function test_tenant_dashboard_renders_staff_name_on_pill(): void
    {
        $html = $this->renderTenantDashboard([
            'todaySchedule' => [
                [
                    'id' => 'test-id',
                    'start_datetime' => date('Y-m-d 14:00:00'),
                    'end_datetime' => date('Y-m-d 14:30:00'),
                    'status' => 'confirmed',
                    'booking_pattern' => 'timeslot',
                    'staff_id' => 'staff-1',
                    'customer_name' => 'Staff Test',
                    'customer_email' => 'staff@test.com',
                    'service_name' => 'Consultation',
                    'service_color' => null,
                    'staff_name' => 'Dr. Sarah Jones',
                ],
            ],
        ]);

        $this->assertStringContainsString('Dr. Sarah Jones', $html);
        $this->assertStringContainsString('vb-schedule-pill-staff', $html);
    }

    public function test_tenant_dashboard_renders_status_chips_in_next_up(): void
    {
        $html = $this->renderTenantDashboard([
            'upcoming' => [
                [
                    'id' => 'upcoming-test-id',
                    'customer_name' => 'Upcoming Jane',
                    'service_name' => 'Facial',
                    'start_datetime' => date('Y-m-d 15:00:00', strtotime('+1 day')),
                    'end_datetime' => date('Y-m-d 15:30:00', strtotime('+1 day')),
                    'status' => 'confirmed',
                ],
            ],
        ]);

        $this->assertStringContainsString('vb-status vb-status-confirmed', $html);
    }

    // ════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════

    private function renderOperatorDashboard(array $overrides = []): string
    {
        $defaults = [
            'user' => ['name' => 'Test Operator', 'type' => 'operator'],
            'version' => '1.0.0-test',
            'activeTenants' => 5,
            'tenantCounts' => ['active' => 5, 'total' => 6, 'paused' => 1, 'archived' => 0],
            'todayBookings' => 12,
            'weekBookings' => 45,
            'upcoming24h' => 3,
            'deltaToday' => 0,
            'deltaWeek' => 0,
            'upcoming' => [],
        ];

        return $this->renderTemplate(
            $this->templateDir . '/admin/dashboard.php',
            array_merge($defaults, $overrides)
        );
    }

    private function renderTenantDashboard(array $overrides = []): string
    {
        $defaults = [
            'user' => ['name' => 'Test Owner', 'type' => 'business'],
            'version' => '1.0.0-test',
            'csrfToken' => 'test-csrf-token',
            'pageTitle' => 'Test Salon',
            'activePage' => 'dashboard',
            'tenant' => ['id' => 'test-tenant-id', 'name' => 'Test Salon', 'slug' => 'test-salon'],
            'todayBookings' => 5,
            'weekBookings' => 20,
            'statusCounts' => ['confirmed' => 8, 'completed' => 15, 'pending' => 2, 'cancelled' => 1, 'no_show' => 0, 'rescheduled' => 0],
            'upcoming' => [],
            'deltaToday' => 0,
            'deltaWeek' => 0,
            'todaySchedule' => [],
        ];

        return $this->renderTemplate(
            $this->templateDir . '/admin/dashboard-business.php',
            array_merge($defaults, $overrides)
        );
    }

    /**
     * Render a dashboard template and return its $content variable.
     *
     * The template does: ob_start() → HTML → $content = ob_get_clean(); include layout.php;
     *
     * We wrap the entire include in an outer output buffer so the layout
     * output (which will error) is captured and discarded. The $content
     * variable is set by the template's own ob_get_clean() call.
     */
    private function renderTemplate(string $path, array $vars): string
    {
        if (!file_exists($path)) {
            $this->markTestSkipped("Template not found: {$path}");
        }

        // Track the OB level before we start
        $levelBefore = ob_get_level();

        // Extract template variables into local scope
        extract($vars);

        // Wrap everything — template + layout include — in an outer buffer
        ob_start();

        try {
            include $path;
        } catch (\Throwable) {
            // Layout include will fail (missing full app context).
            // That's fine — $content was already set.
        }

        // Discard any output (layout partial output, errors, etc.)
        while (ob_get_level() > $levelBefore) {
            ob_end_clean();
        }

        // $content was set by the template's internal ob_get_clean()
        return $content ?? '';
    }
}
