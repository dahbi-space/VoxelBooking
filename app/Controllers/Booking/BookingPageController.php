<?php

declare(strict_types=1);

namespace App\Controllers\Booking;

use App\Engine\Database;
use App\Engine\BrandColorHelper;
use App\Engine\DemoMode;
use App\Engine\Locale;
use App\Engine\Request;
use App\Engine\Response;
use App\Middleware\CsrfMiddleware;

/**
 * Public booking page controller.
 *
 * Serves the booking page shell for a tenant.
 * The page is a single-page application: the shell template loads
 * a JSON config blob and the booking.js orchestrator handles all
 * step transitions client-side via the public API.
 */
final class BookingPageController
{
    /**
     * GET /book/{slug} — render the booking page shell.
     */
    public function show(Request $request): Response
    {
        $slug = $request->getAttribute('slug');

        $tenant = Database::query(
            'SELECT * FROM `tenants` WHERE `slug` = ? AND `status` = ? LIMIT 1',
            [$slug, 'active']
        );

        if (empty($tenant)) {
            ob_start();
            require __DIR__ . '/../../../templates/booking/404.php';
            return Response::html(ob_get_clean(), 404);
        }

        $tenant = $tenant[0];

        // Calculate brand tokens
        $brandTokens = BrandColorHelper::derive($tenant['brand_color'] ?? '#2563EB');
        $brandStyle = BrandColorHelper::inlineStyle($tenant['brand_color'] ?? '#2563EB');

        // Resolve locale: override → browser Accept-Language → tenant default → 'en'
        $acceptLang = $request->header('Accept-Language');
        $resolvedLocale = Locale::resolveForBooking($tenant, $acceptLang);

        // Build tenant config for the JavaScript app
        $tenantConfig = [
            'slug'                  => $tenant['slug'],
            'name'                  => $tenant['name'],
            'timezone'              => $tenant['timezone'],
            'locale'                => $resolvedLocale,
            'currency'              => $tenant['currency'],
            'booking_pattern'       => $tenant['booking_pattern'],
            'require_phone'         => (bool) $tenant['require_phone'],
            'requires_consent'      => (bool) $tenant['requires_consent'],
            'consent_text'          => $tenant['consent_text'] ?: __('booking.form.consent_default'),
            'privacy_policy_url'    => $tenant['privacy_policy_url'] ?: null,
            'custom_fields'         => json_decode($tenant['custom_fields'] ?? '[]', true) ?: [],
            'brand_color'           => $tenant['brand_color'],
            'brand_text'            => $brandTokens['brand_text'],
            'is_demo'               => DemoMode::isActive(),
            'booking_page_heading'  => $tenant['booking_page_heading'] ?: null,
            'booking_page_description' => $tenant['booking_page_description'] ?: null,
            'confirmation_message'  => $tenant['confirmation_message'] ?: null,
            'cancellation_policy'   => $tenant['cancellation_policy'] ?: null,
            'allow_cancellation'    => (bool) ($tenant['allow_cancellation'] ?? true),
            'allow_rescheduling'    => (bool) ($tenant['allow_rescheduling'] ?? true),
        ];

        // Capacity pattern: inject party size bounds from slot configuration
        if ($tenant['booking_pattern'] === 'capacity') {
            $partyBounds = Database::query(
                'SELECT MIN(`min_party_size`) AS `min_ps`, MAX(`max_party_size`) AS `max_ps` FROM `capacity_slots` WHERE `tenant_id` = ? AND `is_active` = 1',
                [$tenant['id']]
            );
            $tenantConfig['min_party_size'] = (int) ($partyBounds[0]['min_ps'] ?? 1);
            $tenantConfig['max_party_size'] = (int) ($partyBounds[0]['max_ps'] ?? 8);
        }

        // Inject translations and formatting config for JS
        $translations = Locale::getTranslationsForDomain('booking');
        $formatting   = Locale::getFormattingConfig();

        // Generate CSRF token
        $csrfToken = CsrfMiddleware::generateToken();

        // Render template to string
        ob_start();
        require __DIR__ . '/../../../templates/booking/page.php';
        $html = ob_get_clean();

        return Response::html($html);
    }
}
