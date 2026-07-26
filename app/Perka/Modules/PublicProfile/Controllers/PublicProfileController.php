<?php

declare(strict_types=1);

namespace App\Perka\Modules\PublicProfile\Controllers;

use App\Engine\Request;
use App\Engine\Response;
use App\Perka\Modules\PublicProfile\Services\PublicProfileService;
use App\Perka\Shared\PerkaView;

/**
 * Public business profile page controller.
 *
 * GET /business/{slug} — renders a tenant's PUBLISHED profile as a standalone,
 * SEO-oriented page. Identity (name, cover, timezone) comes from the tenant;
 * marketing content from the profile.
 */
final class PublicProfileController
{
    public function show(Request $request): Response
    {
        $slug = (string) $request->getAttribute('slug');

        $data = (new PublicProfileService())->getPublishedBySlug($slug);

        // Uniform 404: unknown slug, no profile row, and unpublished profile are
        // indistinguishable to visitors — no existence leak.
        if ($data === null) {
            return PerkaView::response('PublicProfile', 'public/404', [], 404);
        }

        return PerkaView::response('PublicProfile', 'public/profile', [
            'tenant'   => $data['tenant'],
            'profile'  => $data['profile'],
            'services' => $data['services'],
            'hours'    => $data['hours'],
            'slug'     => $slug,
        ]);
    }
}
