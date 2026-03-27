<?php

declare(strict_types=1);

namespace App\Controllers\Booking;

use App\Engine\Database;
use App\Engine\BrandColorHelper;
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

        // Build tenant config for the JavaScript app
        $tenantConfig = [
            'slug'             => $tenant['slug'],
            'name'             => $tenant['name'],
            'timezone'         => $tenant['timezone'],
            'locale'           => $tenant['locale'],
            'currency'         => $tenant['currency'],
            'booking_pattern'  => $tenant['booking_pattern'],
            'require_phone'    => (bool) $tenant['require_phone'],
            'requires_consent' => (bool) $tenant['requires_consent'],
            'consent_text'     => $tenant['consent_text'] ?: 'I agree to the processing of my personal data for this booking.',
            'privacy_policy_url' => $tenant['privacy_policy_url'] ?: null,
            'custom_fields'    => json_decode($tenant['custom_fields'] ?? '[]', true) ?: [],
            'brand_color'      => $tenant['brand_color'],
            'brand_text'       => $brandTokens['brand_text'],
        ];

        // Generate CSRF token
        $csrfToken = CsrfMiddleware::generateToken();

        // Render template to string
        ob_start();
        require __DIR__ . '/../../../templates/booking/page.php';
        $html = ob_get_clean();

        return Response::html($html);
    }
}
