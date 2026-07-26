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

// Read-only booking CTA + services (no booking/availability logic invoked).
$services = is_array($services ?? null) ? $services : [];
$currency = (string) ($tenant['currency'] ?? 'EUR');
// Gate the CTA on tenant status: a paused/archived tenant's /book/{slug} would 404,
// so don't emit a button that won't work.
$showCta  = (string) ($tenant['status'] ?? '') === 'active';
$bookUrl  = '/book/' . rawurlencode($slug);

$fmtDuration = static function (int $min): string {
    if ($min <= 0) {
        return '';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($h > 0 && $m > 0) {
        return "{$h}h {$m}m";
    }
    return $h > 0 ? "{$h}h" : "{$m} min";
};
$fmtPrice = static function (array $svc) use ($currency): string {
    $label = trim((string) ($svc['price_label'] ?? ''));
    if ($label !== '') {
        return $label;
    }
    $price = $svc['price'] ?? null;
    if ($price === null || $price === '') {
        return '';
    }
    $num = preg_replace('/\.00$/', '', number_format((float) $price, 2));
    return $num . ' ' . $currency;
};

// Read-only opening hours (grouped by weekday 0=Mon..6=Sun; pure read).
$hours      = is_array($hours ?? null) ? $hours : [];
$timeFormat = (string) ($tenant['time_format'] ?? '24h');
$timezone   = (string) ($tenant['timezone'] ?? 'UTC');
$dayNames   = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Today's weekday in the tenant's timezone, as 0=Mon..6=Sun (null if tz invalid).
$todayDow = null;
try {
    $todayDow = (int) (new DateTime('now', new DateTimeZone($timezone)))->format('N') - 1;
} catch (\Throwable) {
    $todayDow = null;
}

$fmtTime = static function (string $t) use ($timeFormat): string {
    $dt = DateTime::createFromFormat('H:i:s', $t) ?: DateTime::createFromFormat('H:i', $t);
    if ($dt === false) {
        return $t;
    }
    return $timeFormat === '12h' ? $dt->format('g:i A') : $dt->format('H:i');
};

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
        .pk-cta { display: inline-block; background: #2563EB; color: #fff; text-decoration: none;
                  font-weight: 600; padding: .7rem 1.5rem; border-radius: 8px; }
        .pk-cta:hover { background: #1d4ed8; }
        .pk-cta-hero { margin: 0 0 1.75rem; }
        .pk-cta-services { margin-top: 1.25rem; }
        .pk-services { list-style: none; padding: 0; margin: 0; border-top: 1px solid #eaeaea; }
        .pk-service { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem;
                      padding: .75rem 0; border-bottom: 1px solid #eaeaea; }
        .pk-service-name { font-weight: 600; }
        .pk-service-meta { color: #666; white-space: nowrap; }
        .pk-hours { list-style: none; padding: 0; margin: 0; }
        .pk-hours-row { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; padding: .4rem 0; }
        .pk-day { color: #444; }
        .pk-times { color: #666; white-space: nowrap; }
        .pk-hours-row.pk-today { font-weight: 700; }
        .pk-hours-row.pk-today .pk-day, .pk-hours-row.pk-today .pk-times { color: #1a1a1a; }
        @media (prefers-color-scheme: dark) {
            body { color: #ececec; background: #111; }
            .pk-headline { color: #b5b5b5; }
            .pk-hero { background: #1c1c1e; }
            .pk-services, .pk-service { border-color: #2a2a2a; }
            .pk-service-meta { color: #aaa; }
            .pk-day { color: #cfcfcf; }
            .pk-times { color: #aaa; }
            .pk-hours-row.pk-today .pk-day, .pk-hours-row.pk-today .pk-times { color: #ececec; }
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

        <?php if ($showCta): ?>
        <div class="pk-cta-hero">
            <a class="pk-cta" href="<?= $e($bookUrl) ?>">Book now</a>
        </div>
        <?php endif; ?>

        <?php if ($about !== ''): ?>
        <div class="pk-about"><?= $e($about) ?></div>
        <?php endif; ?>

        <?php if ($services !== []): ?>
        <div class="pk-section-title">Services</div>
        <ul class="pk-services">
            <?php foreach ($services as $svc): ?>
                <?php
                $svcName = trim((string) ($svc['name'] ?? ''));
                if ($svcName === '') {
                    continue;
                }
                $duration = $fmtDuration((int) ($svc['duration_minutes'] ?? 0));
                $price    = $fmtPrice($svc);
                $meta = array_filter([$duration, $price], static fn ($v) => $v !== '');
                ?>
                <li class="pk-service">
                    <span class="pk-service-name"><?= $e($svcName) ?></span>
                    <?php if ($meta !== []): ?>
                    <span class="pk-service-meta"><?= $e(implode(' · ', $meta)) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($showCta): ?>
        <div class="pk-cta-services">
            <a class="pk-cta" href="<?= $e($bookUrl) ?>">Book now</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($hours !== []): ?>
        <div class="pk-section-title">Opening hours</div>
        <ul class="pk-hours">
            <?php for ($d = 0; $d <= 6; $d++): ?>
                <?php
                $ranges  = $hours[$d] ?? [];
                $isToday = ($todayDow === $d);
                $label   = 'Closed';
                if ($ranges !== []) {
                    $parts = [];
                    foreach ($ranges as $r) {
                        $parts[] = $fmtTime((string) $r['start_time']) . '–' . $fmtTime((string) $r['end_time']);
                    }
                    $label = implode(', ', $parts);
                }
                ?>
                <li class="pk-hours-row<?= $isToday ? ' pk-today' : '' ?>">
                    <span class="pk-day"><?= $e($dayNames[$d]) ?><?= $isToday ? ' (today)' : '' ?></span>
                    <span class="pk-times"><?= $e($label) ?></span>
                </li>
            <?php endfor; ?>
        </ul>
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
