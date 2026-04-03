<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Template output tests for the public booking page shell.
 *
 * Verifies resource/capacity-specific back labels and guest-limit hints
 * are wired to the correct states in the template.
 */
final class BookingPageOutputTest extends TestCase
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

    public function test_resource_date_step_uses_generic_back_label(): void
    {
        $html = $this->renderPage();

        $this->assertMatchesRegularExpression(
            '/goBack\(resourceDateBackTarget\)".*t\(\'back\.generic\'\)/s',
            $html
        );
    }

    public function test_resource_guest_step_renders_max_guest_hint(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('guestCount >= guestMax', $html);
        $this->assertStringContainsString("t('resource.max_guests_reached').replace(':count', guestMax)", $html);
    }

    public function test_details_step_uses_pattern_aware_back_label(): void
    {
        $html = $this->renderPage();

        $this->assertMatchesRegularExpression(
            '/goBack\(activeDetailsBackTarget\)".*activeDetailsBackLabel/s',
            $html
        );
    }

    public function test_capacity_party_size_step_renders_max_party_hint(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('partySize >= maxPartySize', $html);
        $this->assertStringContainsString("t('capacity.max_party_size_reached').replace(':count', maxPartySize)", $html);
    }

    private function renderPage(array $overrides = []): string
    {
        $tenant = array_merge([
            'id'                       => 'test-tenant-id',
            'slug'                     => 'test-tenant',
            'name'                     => 'Test Tenant',
            'logo_path'                => null,
            'booking_page_heading'     => null,
            'booking_page_description' => null,
        ], $overrides['tenant'] ?? []);

        $tenantConfig = array_merge([
            'slug'            => 'test-tenant',
            'locale'          => 'en',
            'booking_pattern' => 'resource',
            'timezone'        => 'Europe/Amsterdam',
            'is_demo'         => false,
        ], $overrides['tenantConfig'] ?? []);

        $vars = array_merge([
            'tenant'       => $tenant,
            'tenantConfig' => $tenantConfig,
            'brandStyle'   => ':root { --vb-brand: #2563EB; }',
            'translations' => [],
            'formatting'   => [],
            'csrfToken'    => 'test-csrf-token',
        ], $overrides);

        ob_start();
        extract($vars, EXTR_SKIP);
        require $this->templateDir . '/booking/page.php';
        return (string) ob_get_clean();
    }
}
