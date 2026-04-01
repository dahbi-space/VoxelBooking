<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Template output tests for the bookings index page.
 *
 * Renders the bookings/index.php template with mock data and asserts
 * the correct CSS classes and HTML patterns are present.
 */
final class BookingsIndexOutputTest extends TestCase
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
    // Status chips
    // ════════════════════════════════════════════════════════════════

    public function test_bookings_index_renders_status_chips(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking(['status' => 'confirmed'])],
        ]);

        $this->assertStringContainsString('vb-status vb-status-confirmed', $html);
        $this->assertStringNotContainsString('vb-badge', $html);
    }

    // ════════════════════════════════════════════════════════════════
    // Cell stacks
    // ════════════════════════════════════════════════════════════════

    public function test_bookings_index_renders_cell_stacks(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking()],
        ]);

        $this->assertStringContainsString('vb-cell-primary', $html);
        $this->assertStringContainsString('vb-cell-secondary', $html);
    }

    // ════════════════════════════════════════════════════════════════
    // Sort headers
    // ════════════════════════════════════════════════════════════════

    public function test_bookings_index_renders_sort_headers(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking()],
        ]);

        // All sortable columns must have a sort link
        $this->assertStringContainsString('vb-th-sort', $html);
        // Check for sort params in href
        $this->assertStringContainsString('sort=customer', $html);
        $this->assertStringContainsString('sort=service', $html);
        $this->assertStringContainsString('sort=start_datetime', $html);
        $this->assertStringContainsString('sort=status', $html);
    }

    public function test_bookings_index_renders_active_sort_indicator(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking()],
            'sort'     => 'customer',
            'direction' => 'asc',
            'filters'  => ['status' => null, 'from' => null, 'to' => null, 'search' => null, 'sort' => 'customer', 'direction' => 'asc'],
        ]);

        $this->assertStringContainsString('is-asc', $html);
    }

    public function test_bookings_index_renders_desc_sort_indicator(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking()],
            'sort'     => 'service',
            'direction' => 'desc',
            'filters'  => ['status' => null, 'from' => null, 'to' => null, 'search' => null, 'sort' => 'service', 'direction' => 'desc'],
        ]);

        // The active column shows is-desc, and its toggle link flips to asc
        $this->assertStringContainsString('is-desc', $html);
    }

    // ════════════════════════════════════════════════════════════════
    // Filter pills
    // ════════════════════════════════════════════════════════════════

    public function test_bookings_index_renders_filter_pills(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking()],
        ]);

        $this->assertStringContainsString('vb-filter-pills', $html);
        $this->assertStringContainsString('vb-filter-pill', $html);
    }

    public function test_bookings_index_renders_active_filter_pill(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking(['status' => 'confirmed'])],
            'filters'  => ['status' => 'confirmed', 'from' => null, 'to' => null, 'search' => null, 'sort' => 'start_datetime', 'direction' => 'desc'],
        ]);

        // The confirmed pill must have is-active; the All pill must not
        $this->assertMatchesRegularExpression('/vb-filter-pill\s+is-active[^>]*>[\s\S]*?(?:Confirmed|status_confirmed)/', $html);
    }

    // ════════════════════════════════════════════════════════════════
    // Hidden status input (form-state preservation)
    // ════════════════════════════════════════════════════════════════

    public function test_bookings_index_renders_hidden_status_input_when_active(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking()],
            'filters'  => ['status' => 'confirmed', 'from' => null, 'to' => null, 'search' => null, 'sort' => 'start_datetime', 'direction' => 'desc'],
        ]);

        $this->assertStringContainsString('type="hidden" name="status" value="confirmed"', $html);
    }

    public function test_bookings_index_omits_hidden_status_input_when_empty(): void
    {
        $html = $this->renderBookingsIndex([
            'bookings' => [$this->makeBooking()],
            'filters'  => ['status' => null, 'from' => null, 'to' => null, 'search' => null, 'sort' => 'start_datetime', 'direction' => 'desc'],
        ]);

        $this->assertStringNotContainsString('name="status"', $html);
    }

    // ════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════

    private function makeBooking(array $overrides = []): array
    {
        return array_merge([
            'id'             => 'test-booking-id',
            'customer_name'  => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'service_name'   => 'Haircut',
            'start_datetime' => date('Y-m-d 10:00:00', strtotime('+1 day')),
            'end_datetime'   => date('Y-m-d 10:30:00', strtotime('+1 day')),
            'status'         => 'confirmed',
            'tenant_name'    => 'Test Salon',
            'tenant_slug'    => 'test-salon',
            'tenant_id'      => 'test-tenant-id',
        ], $overrides);
    }

    private function renderBookingsIndex(array $overrides = []): string
    {
        $defaults = [
            'user'             => ['name' => 'Test Operator', 'type' => 'operator'],
            'version'          => '1.0.0-test',
            'csrfToken'        => 'test-csrf-token',
            'pageTitle'        => 'Bookings',
            'activePage'       => 'bookings',
            'bookings'         => [],
            'total'            => 1,
            'page'             => 1,
            'perPage'          => 25,
            'showTenantColumn' => true,
            'filters'          => ['status' => null, 'from' => null, 'to' => null, 'search' => null, 'sort' => 'start_datetime', 'direction' => 'desc'],
            'sort'             => 'start_datetime',
            'direction'        => 'desc',
            'flash'            => null,
            'backUrl'          => '/admin/bookings',
        ];

        return $this->renderTemplate(
            $this->templateDir . '/admin/bookings/index.php',
            array_merge($defaults, $overrides)
        );
    }

    /**
     * Render a template and return its $content variable.
     */
    private function renderTemplate(string $path, array $vars): string
    {
        if (!file_exists($path)) {
            $this->markTestSkipped("Template not found: {$path}");
        }

        $levelBefore = ob_get_level();
        extract($vars);

        ob_start();

        try {
            include $path;
        } catch (\Throwable) {
            // Layout include will fail — $content was already set
        }

        while (ob_get_level() > $levelBefore) {
            ob_end_clean();
        }

        return $content ?? '';
    }
}
