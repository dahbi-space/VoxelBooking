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

// Presentation-only (uses fields already present on $tenant; no new read).
// Logo is opt-in; falls back to name initials when absent or missing on disk
// (checked server-side via $hasFile below) — never a broken image, no JS.
$logo = trim((string) ($tenant['logo_path'] ?? ''));
$initials = '';
foreach (preg_split('/\s+/', $name) ?: [] as $w) {
    if ($w !== '') {
        $initials .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    if (mb_strlen($initials) >= 2) {
        break;
    }
}
if ($initials === '') {
    $initials = '·';
}

// Team (read-only staff). Reuses the hero avatar/initials treatment per member.
$team = is_array($team ?? null) ? $team : [];
$initialsOf = static function (string $n): string {
    $out = '';
    foreach (preg_split('/\s+/', trim($n)) ?: [] as $w) {
        if ($w !== '') {
            $out .= mb_strtoupper(mb_substr($w, 0, 1));
        }
        if (mb_strlen($out) >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : '·';
};

// Server-side avatar existence check — guarantees "never a broken image"
// without JS (the page CSP forbids inline scripts / onerror handlers).
$publicRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($publicRoot === '' || !is_dir($publicRoot)) {
    $publicRoot = dirname(__DIR__, 6) . '/public';
}
$hasFile = static function (string $rel) use ($publicRoot): bool {
    $rel = ltrim($rel, '/');
    return $rel !== '' && is_file($publicRoot . '/' . $rel);
};

// Read-only reviews + aggregate (pure reads over perka_reviews; no logic).
$reviews   = is_array($reviews ?? null) ? $reviews : [];
$aggregate = is_array($aggregate ?? null) ? $aggregate : ['count' => 0, 'average' => null];
$dateFormat = trim((string) ($tenant['date_format'] ?? '')) ?: 'M j, Y';

// Unicode star bar (CSP-safe: no icon fonts, no JS). $filled filled ★, the
// remainder to 5 empty ☆. Clamped to 0..5.
$stars = static function (int $filled): string {
    $filled = max(0, min(5, $filled));
    return str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);
};

// Format a review's display date from reviewed_at, falling back to created_at.
$fmtReviewDate = static function (array $r) use ($dateFormat): string {
    $raw = trim((string) ($r['reviewed_at'] ?? '')) ?: trim((string) ($r['created_at'] ?? ''));
    if ($raw === '') {
        return '';
    }
    try {
        return (new DateTime($raw))->format($dateFormat);
    } catch (\Throwable) {
        return '';
    }
};

// "View all" points at the dedicated reviews page (a separate increment). Shown
// only when there are more published reviews than the 4 rendered here, so it is
// never a dead link on small rosters.
$reviewsUrl   = '/business/' . rawurlencode($slug) . '/reviews';
$showViewAll  = (int) $aggregate['count'] > count($reviews);

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
        :root {
            color-scheme: light dark;
            --pk-bg: #f5f6f8;
            --pk-surface: #ffffff;
            --pk-surface-2: #eef0f3;
            --pk-border: #e5e7eb;
            --pk-text: #16181d;
            --pk-muted: #6b7280;
            --pk-accent: #2563EB;
            --pk-accent-hover: #1d4ed8;
            --pk-on-accent: #ffffff;
            --pk-accent-soft: rgba(37, 99, 235, .10);
            --pk-radius: 16px;
            --pk-radius-sm: 10px;
            --pk-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 6px 20px rgba(16, 24, 40, .07);
            --pk-maxw: 900px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --pk-bg: #0e0f12;
                --pk-surface: #17191e;
                --pk-surface-2: #20232a;
                --pk-border: #2a2d34;
                --pk-text: #e9eaee;
                --pk-muted: #9aa1ac;
                --pk-accent: #5b8cff;
                --pk-accent-hover: #79a1ff;
                --pk-on-accent: #0e0f12;
                --pk-accent-soft: rgba(91, 140, 255, .16);
                --pk-shadow: 0 1px 2px rgba(0, 0, 0, .4), 0 8px 24px rgba(0, 0, 0, .38);
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               line-height: 1.55; color: var(--pk-text); background: var(--pk-bg); -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; }

        /* Hero banner — gradient fallback means a missing/broken cover is never a grey box */
        .pk-hero { position: relative; height: clamp(150px, 32vw, 300px); overflow: hidden;
                   background: linear-gradient(135deg, var(--pk-accent-soft), var(--pk-surface-2)); }
        .pk-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
        .pk-hero::after { content: ""; position: absolute; inset: 0;
                          background: linear-gradient(to bottom, rgba(0,0,0,0) 55%, rgba(0,0,0,.25)); }

        .pk-wrap { max-width: var(--pk-maxw); margin: 0 auto; padding: 0 1rem 5rem; }

        /* Header card overlapping the banner */
        .pk-header { position: relative; margin-top: -60px; background: var(--pk-surface);
                     border: 1px solid var(--pk-border); border-radius: var(--pk-radius); box-shadow: var(--pk-shadow);
                     padding: 1.15rem 1.35rem 1.35rem; display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
        .pk-avatar { width: 84px; height: 84px; border-radius: 50%; flex: 0 0 auto; margin-top: -54px;
                     border: 4px solid var(--pk-surface); background: var(--pk-surface-2); box-shadow: var(--pk-shadow);
                     overflow: hidden; display: flex; align-items: center; justify-content: center;
                     font-weight: 700; font-size: 1.7rem; color: var(--pk-muted); }
        .pk-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .pk-id { flex: 1 1 220px; min-width: 0; background: transparent; }
        .pk-name { font-size: clamp(1.5rem, 4vw, 2.05rem); font-weight: 750; line-height: 1.15;
                   margin: 0 0 .2rem; background: transparent; }
        .pk-headline { font-size: 1.05rem; color: var(--pk-muted); margin: 0; background: transparent; }
        .pk-header-cta { flex: 0 0 auto; }

        .pk-cta { display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
                  background: var(--pk-accent); color: var(--pk-on-accent); text-decoration: none; font-weight: 650;
                  padding: .72rem 1.4rem; border-radius: var(--pk-radius-sm); white-space: nowrap; transition: background .15s; }
        .pk-cta:hover { background: var(--pk-accent-hover); }

        /* Section cards */
        .pk-card { background: var(--pk-surface); border: 1px solid var(--pk-border); border-radius: var(--pk-radius);
                   box-shadow: var(--pk-shadow); padding: 1.2rem 1.35rem; margin-top: 1rem; }
        .pk-section-title { font-size: 1.05rem; font-weight: 700; letter-spacing: -.01em; color: var(--pk-text); margin: 0 0 .85rem; }
        .pk-about { white-space: pre-line; color: var(--pk-text); margin: 0; font-size: 1.02rem; }

        .pk-services { list-style: none; padding: 0; margin: 0; }
        .pk-service { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem;
                      padding: .8rem 0; border-top: 1px solid var(--pk-border); }
        .pk-service:first-child { border-top: 0; }
        .pk-service-name { font-weight: 600; }
        .pk-service-meta { color: var(--pk-muted); white-space: nowrap; }
        .pk-cta-services { margin-top: 1.1rem; }

        .pk-hours { list-style: none; padding: 0; margin: 0; }
        .pk-hours-row { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem;
                        padding: .5rem .6rem; border-radius: var(--pk-radius-sm); }
        .pk-day { color: var(--pk-text); }
        .pk-times { color: var(--pk-muted); white-space: nowrap; }
        .pk-hours-row.pk-today { background: var(--pk-accent-soft); font-weight: 700; }
        .pk-hours-row.pk-today .pk-day, .pk-hours-row.pk-today .pk-times { color: var(--pk-text); }

        /* Reviews — Unicode star bar (CSP-safe), aggregate header, review list */
        .pk-reviews-head { display: flex; align-items: baseline; flex-wrap: wrap; gap: .5rem .75rem; margin-bottom: 1rem; }
        .pk-stars { color: #f5a623; letter-spacing: .06em; font-size: 1.05rem; line-height: 1; white-space: nowrap; }
        .pk-rating-num { font-weight: 700; color: var(--pk-text); }
        .pk-rating-count { color: var(--pk-muted); }
        .pk-reviews { list-style: none; padding: 0; margin: 0; }
        .pk-review { padding: 1rem 0; border-top: 1px solid var(--pk-border); }
        .pk-review:first-child { border-top: 0; }
        .pk-review .pk-stars { font-size: .95rem; }
        .pk-review-body { margin: .5rem 0 .55rem; color: var(--pk-text); white-space: pre-line; line-height: 1.5; }
        .pk-review-meta { color: var(--pk-muted); font-size: .9rem; }
        .pk-review-author { font-weight: 600; color: var(--pk-text); }
        .pk-reviews-all { margin-top: 1.1rem; }
        .pk-reviews-all a { color: var(--pk-accent); font-weight: 600; text-decoration: none; }
        .pk-reviews-all a:hover { text-decoration: underline; }

        .pk-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(clamp(140px, 22vw, 190px), 1fr)); gap: .6rem; }
        .pk-gallery img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: var(--pk-radius-sm);
                          display: block; transition: transform .2s; }
        .pk-gallery img:hover { transform: scale(1.02); }

        /* Team — reuses .pk-avatar for the circle/initials; only the grid + cell are new */
        .pk-team { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 1rem .75rem; }
        .pk-teammate { display: flex; flex-direction: column; align-items: center; text-align: center; gap: .5rem; }
        .pk-teammate .pk-avatar { width: 72px; height: 72px; margin-top: 0; font-size: 1.4rem; }
        .pk-teammate-name { font-weight: 600; line-height: 1.25; }
        .pk-teammate-title { color: var(--pk-muted); font-size: .9rem; line-height: 1.2; }
        /* Team member is an anchor trigger (#modal-id) — CSS :target modal, no JS */
        .pk-teammate { display: flex; flex-direction: column; align-items: center; text-align: center; gap: .5rem;
                       text-decoration: none; color: inherit; cursor: pointer; padding: .25rem; }
        .pk-teammate:hover .pk-avatar { transform: scale(1.04); transition: transform .15s; }
        .pk-teammate:focus-visible { outline: 2px solid var(--pk-accent); outline-offset: 2px; border-radius: var(--pk-radius-sm); }

        /* Team detail modal — opened via :target (CSP forbids inline JS) */
        .pk-modal { position: fixed; inset: 0; z-index: 100; display: none; align-items: center; justify-content: center; padding: 1rem; }
        .pk-modal:target { display: flex; }
        .pk-modal-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .5); animation: pkFade .18s ease; }
        .pk-modal-card { position: relative; z-index: 1; width: 100%; max-width: 480px; max-height: 85vh; overflow-y: auto;
                         background: var(--pk-surface); border: 1px solid var(--pk-border); border-radius: var(--pk-radius);
                         box-shadow: var(--pk-shadow); padding: 1.6rem 1.4rem 1.4rem; animation: pkPop .18s ease; }
        @keyframes pkFade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pkPop { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .pk-modal-close { position: absolute; top: .6rem; right: .6rem; width: 34px; height: 34px; border: 0; border-radius: 50%;
                          background: var(--pk-surface-2); color: var(--pk-text); font-size: 1.1rem; line-height: 1; cursor: pointer; }
        .pk-modal-close:hover { background: var(--pk-border); }
        .pk-modal-head { display: flex; flex-direction: column; align-items: center; text-align: center; gap: .35rem; }
        .pk-modal-head .pk-avatar { width: 96px; height: 96px; margin-top: 0; font-size: 1.9rem; }
        .pk-modal-name { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
        .pk-modal-title { color: var(--pk-muted); }
        .pk-modal-bio { margin-top: 1rem; white-space: pre-line; color: var(--pk-text); }
        .pk-modal-subtitle { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 700;
                             color: var(--pk-muted); margin: 1.35rem 0 .5rem; }
        .pk-modal-book { margin-top: 1.35rem; width: 100%; }
        .pk-modal-close { text-decoration: none; display: flex; align-items: center; justify-content: center; }

        .pk-socials { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: .5rem; }
        .pk-socials a { display: inline-block; padding: .4rem .85rem; border: 1px solid var(--pk-border);
                        border-radius: 999px; color: var(--pk-accent); text-decoration: none; font-weight: 600;
                        font-size: .92rem; background: var(--pk-surface); transition: border-color .15s; }
        .pk-socials a:hover { border-color: var(--pk-accent); }

        /* Mobile sticky Book-now bar */
        .pk-stickybar { display: none; }
        @media (max-width: 640px) {
            .pk-header { align-items: flex-start; }
            .pk-header-cta { display: none; }
            .pk-stickybar { display: block; position: sticky; bottom: 0; z-index: 20;
                            padding: .6rem 1rem calc(.6rem + env(safe-area-inset-bottom));
                            background: var(--pk-surface); border-top: 1px solid var(--pk-border);
                            box-shadow: 0 -6px 18px rgba(0, 0, 0, .10); }
            .pk-stickybar .pk-cta { display: flex; width: 100%; }
        }
    </style>
</head>
<body>
    <header class="pk-hero">
        <?php if ($hasFile($cover)): ?>
        <img class="pk-hero-img" src="/<?= $e(ltrim($cover, '/')) ?>" alt="<?= $e($name) ?>">
        <?php endif; ?>
    </header>

    <main class="pk-wrap">
        <div class="pk-header">
            <?php if ($hasFile($logo)): ?>
            <span class="pk-avatar"><img src="/<?= $e(ltrim($logo, '/')) ?>" alt="<?= $e($name) ?>"></span>
            <?php else: ?>
            <span class="pk-avatar"><?= $e($initials) ?></span>
            <?php endif; ?>
            <div class="pk-id">
                <h1 class="pk-name"><?= $e($name) ?></h1>
                <?php if ($headline !== ''): ?>
                <p class="pk-headline"><?= $e($headline) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($showCta): ?>
            <div class="pk-header-cta">
                <a class="pk-cta" href="<?= $e($bookUrl) ?>">Book now</a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($about !== ''): ?>
        <section class="pk-card">
            <div class="pk-about"><?= $e($about) ?></div>
        </section>
        <?php endif; ?>

        <?php if ($services !== []): ?>
        <section class="pk-card">
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
        </section>
        <?php endif; ?>

        <?php if ($team !== []): ?>
        <section class="pk-card" id="team">
            <div class="pk-section-title">Team</div>
            <div class="pk-team">
                <?php foreach ($team as $member): ?>
                    <?php
                    $mId   = (string) ($member['id'] ?? '');
                    $mName = trim((string) ($member['name'] ?? ''));
                    if ($mId === '' || $mName === '') {
                        continue;
                    }
                    $mTitle  = trim((string) ($member['title'] ?? ''));
                    $mAvatar = trim((string) ($member['avatar_path'] ?? ''));
                    $mInit   = $initialsOf($mName);
                    ?>
                    <a class="pk-teammate" href="#modal-<?= $e($mId) ?>" aria-haspopup="dialog">
                        <?php if ($hasFile($mAvatar)): ?>
                        <span class="pk-avatar"><img src="/<?= $e(ltrim($mAvatar, '/')) ?>" alt="<?= $e($mName) ?>"></span>
                        <?php else: ?>
                        <span class="pk-avatar"><?= $e($mInit) ?></span>
                        <?php endif; ?>
                        <div class="pk-teammate-name"><?= $e($mName) ?></div>
                        <?php if ($mTitle !== ''): ?>
                        <div class="pk-teammate-title"><?= $e($mTitle) ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php /* One hidden modal per member (server-rendered, escaped) */ ?>
        <?php foreach ($team as $member): ?>
            <?php
            $mId   = (string) ($member['id'] ?? '');
            $mName = trim((string) ($member['name'] ?? ''));
            if ($mId === '' || $mName === '') {
                continue;
            }
            $mTitle    = trim((string) ($member['title'] ?? ''));
            $mAvatar   = trim((string) ($member['avatar_path'] ?? ''));
            $mBio      = trim((string) ($member['bio'] ?? ''));
            $mInit     = $initialsOf($mName);
            $mServices = is_array($member['services'] ?? null) ? $member['services'] : [];
            $mFirst    = (preg_split('/\s+/', $mName) ?: [$mName])[0];
            ?>
            <div class="pk-modal" id="modal-<?= $e($mId) ?>" role="dialog" aria-modal="true"
                 aria-labelledby="modal-<?= $e($mId) ?>-name">
                <a class="pk-modal-backdrop" href="#team" aria-label="Close"></a>
                <div class="pk-modal-card">
                    <a class="pk-modal-close" href="#team" aria-label="Close">&times;</a>
                    <div class="pk-modal-head">
                        <?php if ($hasFile($mAvatar)): ?>
                        <span class="pk-avatar"><img src="/<?= $e(ltrim($mAvatar, '/')) ?>" alt="<?= $e($mName) ?>"></span>
                        <?php else: ?>
                        <span class="pk-avatar"><?= $e($mInit) ?></span>
                        <?php endif; ?>
                        <div class="pk-modal-name" id="modal-<?= $e($mId) ?>-name"><?= $e($mName) ?></div>
                        <?php if ($mTitle !== ''): ?>
                        <div class="pk-modal-title"><?= $e($mTitle) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($mBio !== ''): ?>
                    <div class="pk-modal-bio"><?= $e($mBio) ?></div>
                    <?php endif; ?>

                    <?php if ($mServices !== []): ?>
                    <div class="pk-modal-subtitle">Services</div>
                    <ul class="pk-services">
                        <?php foreach ($mServices as $svc): ?>
                            <?php
                            $sName = trim((string) ($svc['name'] ?? ''));
                            if ($sName === '') {
                                continue;
                            }
                            $sMeta = array_filter(
                                [$fmtDuration((int) ($svc['duration_minutes'] ?? 0)), $fmtPrice($svc)],
                                static fn ($v) => $v !== ''
                            );
                            ?>
                            <li class="pk-service">
                                <span class="pk-service-name"><?= $e($sName) ?></span>
                                <?php if ($sMeta !== []): ?>
                                <span class="pk-service-meta"><?= $e(implode(' · ', $sMeta)) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if ($showCta): ?>
                    <a class="pk-cta pk-modal-book" href="<?= $e($bookUrl) ?>">Book with <?= $e($mFirst) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($hours !== []): ?>
        <section class="pk-card">
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
        </section>
        <?php endif; ?>

        <?php if ($reviews !== []): ?>
        <section class="pk-card" id="reviews">
            <div class="pk-section-title">Reviews</div>

            <?php if ($aggregate['average'] !== null): ?>
            <div class="pk-reviews-head">
                <span class="pk-stars" aria-hidden="true"><?= $e($stars((int) floor((float) $aggregate['average']))) ?></span>
                <span class="pk-rating-num"><?= $e(number_format((float) $aggregate['average'], 1)) ?></span>
                <span class="pk-rating-count">based on <?= (int) $aggregate['count'] ?> review<?= (int) $aggregate['count'] === 1 ? '' : 's' ?></span>
            </div>
            <?php endif; ?>

            <ul class="pk-reviews">
                <?php foreach ($reviews as $review): ?>
                    <?php
                    $rBody = trim((string) ($review['body'] ?? ''));
                    if ($rBody === '') {
                        continue;
                    }
                    $rRating = max(1, min(5, (int) ($review['rating'] ?? 0)));
                    $rName   = trim((string) ($review['reviewer_name'] ?? '')) ?: 'Anonymous';
                    $rDate   = $fmtReviewDate($review);
                    ?>
                    <li class="pk-review">
                        <span class="pk-stars" aria-label="<?= $rRating ?> out of 5 stars"><?= $e($stars($rRating)) ?></span>
                        <p class="pk-review-body"><?= $e($rBody) ?></p>
                        <div class="pk-review-meta">
                            <span class="pk-review-author"><?= $e($rName) ?></span><?php if ($rDate !== ''): ?> · <?= $e($rDate) ?><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($showViewAll): ?>
            <div class="pk-reviews-all">
                <a href="<?= $e($reviewsUrl) ?>">View all <?= (int) $aggregate['count'] ?> reviews →</a>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($gallery !== []): ?>
        <section class="pk-card">
            <div class="pk-section-title">Gallery</div>
            <div class="pk-gallery">
                <?php foreach ($gallery as $img): ?>
                    <?php $img = ltrim((string) $img, '/'); if ($img === '') { continue; } ?>
                    <img src="/<?= $e($img) ?>" alt="<?= $e($name) ?>" loading="lazy">
                <?php endforeach; ?>
            </div>
        </section>
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
        <section class="pk-card">
            <div class="pk-section-title">Connect</div>
            <ul class="pk-socials">
                <?php foreach ($socialLinks as $platform => $url): ?>
                <li><a href="<?= $e($url) ?>" rel="nofollow noopener" target="_blank"><?= $e(ucfirst($platform)) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </main>

    <?php if ($showCta): ?>
    <div class="pk-stickybar">
        <a class="pk-cta" href="<?= $e($bookUrl) ?>">Book now</a>
    </div>
    <?php endif; ?>

</body>
</html>
