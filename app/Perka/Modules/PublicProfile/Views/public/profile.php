<?php

declare(strict_types=1);

/**
 * Public business profile page (standalone — no admin layout).
 *
 * Variables:
 *   $tenant  array  core tenant row (name, cover_image_path, timezone, locale, …)
 *   $profile array  decoded profile (headline, about, gallery[], socials{}, seo_*)
 *   $slug    string tenant public slug
 *
 * All dynamic values are escaped. JSON-LD uses JSON_HEX_TAG to prevent
 * </script> breakout.
 */

use App\Perka\Shared\PerkaView;

$tenant  = $tenant  ?? [];
$profile = $profile ?? [];
$slug    = $slug    ?? '';

$name     = (string) ($tenant['name'] ?? '');
$locale   = (string) ($tenant['locale'] ?? 'en');
$cover    = trim((string) ($tenant['cover_image_path'] ?? ''));
$headline = trim((string) ($profile['headline'] ?? ''));
$about    = trim((string) ($profile['about'] ?? ''));
$gallery  = is_array($profile['gallery'] ?? null) ? $profile['gallery'] : [];
$socials  = is_array($profile['socials'] ?? null) ? $profile['socials'] : [];

$seoTitle = trim((string) ($profile['seo_title'] ?? '')) !== ''
    ? (string) $profile['seo_title']
    : $name;
$seoDesc  = trim((string) ($profile['seo_description'] ?? '')) !== ''
    ? (string) $profile['seo_description']
    : ($about !== '' ? mb_substr($about, 0, 155) : $name);

// Absolute canonical / OG url, robust without app config (used for the test too).
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$canonical = $scheme . '://' . $host . '/business/' . rawurlencode($slug);
$coverUrl  = $cover !== '' ? $scheme . '://' . $host . '/' . ltrim($cover, '/') : '';

$e = static fn (mixed $v): string => PerkaView::e($v);

// JSON-LD LocalBusiness (only well-formed fields).
$jsonLd = array_filter([
    '@context'    => 'https://schema.org',
    '@type'       => 'LocalBusiness',
    'name'        => $name,
    'description' => $seoDesc,
    'url'         => $canonical,
    'image'       => $coverUrl ?: null,
    'sameAs'      => array_values(array_filter(
        array_map(static fn ($v) => filter_var((string) $v, FILTER_VALIDATE_URL) ?: null, $socials)
    )) ?: null,
], static fn ($v) => $v !== null && $v !== '' && $v !== []);
?><!DOCTYPE html>
<html lang="<?= $e($locale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($seoTitle) ?></title>
    <meta name="description" content="<?= $e($seoDesc) ?>">
    <link rel="canonical" href="<?= $e($canonical) ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $e($seoTitle) ?>">
    <meta property="og:description" content="<?= $e($seoDesc) ?>">
    <meta property="og:url" content="<?= $e($canonical) ?>">
    <?php if ($coverUrl !== ''): ?>
    <meta property="og:image" content="<?= $e($coverUrl) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= $coverUrl !== '' ? 'summary_large_image' : 'summary' ?>">

    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.6; color: #1a1a1a; background: #fff; }
        .pk-hero { position: relative; background: #f2f2f4; }
        .pk-hero img { display: block; width: 100%; max-height: 340px; object-fit: cover; }
        .pk-wrap { max-width: 860px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        .pk-name { font-size: 2rem; font-weight: 700; margin: 0 0 .25rem; }
        .pk-headline { font-size: 1.15rem; color: #555; margin: 0 0 1.5rem; }
        .pk-about { white-space: pre-line; margin: 0 0 2rem; }
        .pk-section-title { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: #888; margin: 2rem 0 .75rem; }
        .pk-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .75rem; }
        .pk-gallery img { width: 100%; height: 160px; object-fit: cover; border-radius: 8px; }
        .pk-socials { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: .5rem 1rem; }
        .pk-socials a { color: #2563EB; text-decoration: none; }
        .pk-socials a:hover { text-decoration: underline; }
        @media (prefers-color-scheme: dark) {
            body { color: #ececec; background: #111; }
            .pk-headline { color: #b5b5b5; }
            .pk-hero { background: #1c1c1e; }
        }
    </style>
</head>
<body>
    <?php if ($coverUrl !== ''): ?>
    <div class="pk-hero">
        <img src="/<?= $e(ltrim($cover, '/')) ?>" alt="<?= $e($name) ?>">
    </div>
    <?php endif; ?>

    <main class="pk-wrap">
        <h1 class="pk-name"><?= $e($name) ?></h1>
        <?php if ($headline !== ''): ?>
        <p class="pk-headline"><?= $e($headline) ?></p>
        <?php endif; ?>

        <?php if ($about !== ''): ?>
        <div class="pk-about"><?= $e($about) ?></div>
        <?php endif; ?>

        <?php if ($gallery !== []): ?>
        <div class="pk-section-title">Gallery</div>
        <div class="pk-gallery">
            <?php foreach ($gallery as $img): ?>
                <?php $img = ltrim((string) $img, '/'); if ($img === '') { continue; } ?>
                <img src="/<?= $e($img) ?>" alt="<?= $e($name) ?>" loading="lazy">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        $socialLinks = [];
        foreach ($socials as $platform => $value) {
            $url = filter_var((string) $value, FILTER_VALIDATE_URL);
            if ($url !== false && preg_match('#^https?://#i', $url)) {
                $socialLinks[(string) $platform] = $url;
            }
        }
        ?>
        <?php if ($socialLinks !== []): ?>
        <div class="pk-section-title">Connect</div>
        <ul class="pk-socials">
            <?php foreach ($socialLinks as $platform => $url): ?>
            <li><a href="<?= $e($url) ?>" rel="nofollow noopener" target="_blank"><?= $e(ucfirst($platform)) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </main>
</body>
</html>
