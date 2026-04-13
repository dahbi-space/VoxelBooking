<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;

/**
 * SEO controller — robots.txt and sitemap.xml.
 *
 * Both are served via routes (not static files) because the app's front
 * controller handles all requests. Static files in public/ are only served
 * directly when the web server (Apache/Nginx) resolves them before PHP,
 * which is not guaranteed across all hosting configurations.
 */
final class SeoController
{
    /**
     * GET /robots.txt — crawl policy.
     *
     * Allows /book/{slug} (public booking pages) but disallows bearer-token
     * sub-paths (/book/{slug}/manage/*, /book/{slug}/privacy/*) to prevent
     * crawling of tokenized cancel/reschedule/privacy URLs.
     */
    public function robots(Request $request): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /book/',
            'Disallow: /book/*/manage/',
            'Disallow: /book/*/privacy/',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /cron/',
            'Disallow: /install/',
            'Disallow: /embed/',
        ];

        $body = implode("\n", $lines) . "\n";

        return (new Response())
            ->status(200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=86400')
            ->body($body);
    }

    /**
     * GET /sitemap.xml — XML sitemap of all active tenant booking pages.
     *
     * Only lists /book/{slug} pages (indexable). Does not list manage/
     * or privacy/ sub-paths (tokenized, noindex).
     */
    public function sitemap(Request $request): Response
    {
        $baseUrl = rtrim(app_url(), '/');

        try {
            $tenants = Database::query(
                "SELECT `slug` FROM `tenants` WHERE `status` = 'active' ORDER BY `slug` ASC"
            );
        } catch (\Throwable) {
            // Database unavailable (pre-install) — return empty sitemap
            $tenants = [];
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($tenants as $tenant) {
            $loc = htmlspecialchars($baseUrl . '/book/' . $tenant['slug'], ENT_XML1, 'UTF-8');
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$loc}</loc>\n";
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "    <priority>0.8</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return (new Response())
            ->status(200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->body($xml);
    }
}
