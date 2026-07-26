<?php

declare(strict_types=1);

/**
 * Admin: edit a tenant's public business profile (body only).
 *
 * Rendered by PerkaView and injected as $content into the core admin.layout by
 * ProfileController::shell(). Do NOT include the layout here.
 *
 * Variables: $tenantId, $tenant, $profile (decoded array|null), $themes[],
 *            $platforms[], $csrfToken
 */

use App\Perka\Shared\PerkaView;

$tenantId  = $tenantId ?? '';
$tenant    = $tenant ?? [];
$profile   = $profile ?? null;
$themes    = $themes ?? ['default'];
$platforms = $platforms ?? [];
$csrfToken = $csrfToken ?? '';

$e = static fn (mixed $v): string => PerkaView::e($v);

$isPublished = (int) ($profile['is_published'] ?? 0) === 1;
$slug        = (string) ($tenant['slug'] ?? '');
$curTheme    = (string) ($profile['theme'] ?? 'default');
$gallery     = is_array($profile['gallery'] ?? null) ? $profile['gallery'] : [];
$socials     = is_array($profile['socials'] ?? null) ? $profile['socials'] : [];

// Value helper: prefer flashed old() input, then stored profile, then ''.
$val = static function (string $key, mixed $stored) {
    return old($key, $stored ?? '');
};
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title">Business Profile</h2>
        <p class="vb-page-subtitle"><?= $e($tenant['name'] ?? '') ?></p>
    </div>
</div>

<!-- Publish status / toggle (separate form → dedicated routes) -->
<div class="vb-card" style="margin-bottom:1rem;">
    <div class="vb-card-body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <strong><?= $isPublished ? 'Published' : 'Not published' ?></strong>
            <?php if ($isPublished && $slug !== ''): ?>
            &nbsp;·&nbsp;<a href="/business/<?= $e(rawurlencode($slug)) ?>" target="_blank" rel="noopener">View public page ↗</a>
            <?php endif; ?>
        </div>
        <form method="post"
              action="/admin/tenants/<?= $e($tenantId) ?>/profile/<?= $isPublished ? 'unpublish' : 'publish' ?>">
            <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken) ?>">
            <button type="submit" class="vb-btn <?= $isPublished ? 'vb-btn-secondary' : 'vb-btn-primary' ?>">
                <i data-lucide="<?= $isPublished ? 'eye-off' : 'globe' ?>"></i>
                <?= $isPublished ? 'Unpublish' : 'Publish' ?>
            </button>
        </form>
    </div>
</div>

<form method="post"
      action="/admin/tenants/<?= $e($tenantId) ?>/profile"
      enctype="multipart/form-data">
    <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken) ?>">

    <div class="vb-card">
        <div class="vb-card-body">
            <div class="vb-form-group">
                <label class="vb-label" for="pf-headline">Headline</label>
                <input class="vb-input" id="pf-headline" name="headline" type="text" maxlength="255"
                       value="<?= $e($val('headline', $profile['headline'] ?? '')) ?>">
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="pf-about">About</label>
                <textarea class="vb-input vb-textarea" id="pf-about" name="about" rows="5"><?= $e($val('about', $profile['about'] ?? '')) ?></textarea>
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="pf-theme">Theme</label>
                <select class="vb-input" id="pf-theme" name="theme">
                    <?php $selTheme = old('theme', $curTheme); ?>
                    <?php foreach ($themes as $t): ?>
                    <option value="<?= $e($t) ?>" <?= $selTheme === $t ? 'selected' : '' ?>><?= $e(ucfirst($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="vb-card" style="margin-top:1rem;">
        <div class="vb-card-body">
            <div class="vb-card-title">SEO</div>
            <div class="vb-form-group">
                <label class="vb-label" for="pf-seo-title">SEO title</label>
                <input class="vb-input" id="pf-seo-title" name="seo_title" type="text" maxlength="255"
                       value="<?= $e($val('seo_title', $profile['seo_title'] ?? '')) ?>">
            </div>
            <div class="vb-form-group">
                <label class="vb-label" for="pf-seo-desc">SEO description</label>
                <input class="vb-input" id="pf-seo-desc" name="seo_description" type="text" maxlength="255"
                       value="<?= $e($val('seo_description', $profile['seo_description'] ?? '')) ?>">
            </div>
        </div>
    </div>

    <div class="vb-card" style="margin-top:1rem;">
        <div class="vb-card-body">
            <div class="vb-card-title">Social links</div>
            <?php foreach ($platforms as $platform): ?>
            <div class="vb-form-group">
                <label class="vb-label" for="pf-social-<?= $e($platform) ?>"><?= $e(ucfirst($platform)) ?></label>
                <input class="vb-input" id="pf-social-<?= $e($platform) ?>" name="social_<?= $e($platform) ?>"
                       type="url" inputmode="url" placeholder="https://…"
                       value="<?= $e($val("social_{$platform}", $socials[$platform] ?? '')) ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="vb-card" style="margin-top:1rem;">
        <div class="vb-card-body">
            <div class="vb-card-title">Gallery</div>

            <?php if ($gallery !== []): ?>
            <div class="pk-admin-gallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.75rem;margin-bottom:1rem;">
                <?php foreach ($gallery as $img): ?>
                    <?php $img = (string) $img; if ($img === '') { continue; } ?>
                    <label style="display:block;position:relative;">
                        <img src="/<?= $e(ltrim($img, '/')) ?>" alt="" style="width:100%;height:110px;object-fit:cover;border-radius:8px;">
                        <span style="display:flex;align-items:center;gap:.35rem;margin-top:.35rem;font-size:.85rem;">
                            <input type="checkbox" name="remove_gallery[]" value="<?= $e($img) ?>"> Remove
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="vb-form-group">
                <label class="vb-label" for="pf-gallery">Add images</label>
                <input class="vb-input" id="pf-gallery" name="gallery_images[]" type="file"
                       accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                <span class="vb-settings-hint">JPG, PNG, WebP or GIF, up to 2&nbsp;MB each.</span>
            </div>
        </div>
    </div>

    <div class="vb-form-actions" style="margin-top:1rem;">
        <button type="submit" class="vb-btn vb-btn-primary">
            <i data-lucide="save"></i>
            Save
        </button>
    </div>
</form>
