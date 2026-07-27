<?php

declare(strict_types=1);

/**
 * Admin: curate a tenant's public reviews (body only).
 *
 * Rendered by PerkaView and injected as $content into the core admin.layout by
 * ReviewsController::shell(). Do NOT include the layout here.
 *
 * Variables: $tenantId, $tenant, $reviews (list<array>), $csrfToken
 */

use App\Perka\Shared\PerkaView;

$tenantId  = $tenantId ?? '';
$tenant    = $tenant ?? [];
$reviews   = is_array($reviews ?? null) ? $reviews : [];
$csrfToken = $csrfToken ?? '';

$e = static fn (mixed $v): string => PerkaView::e($v);

$base = '/admin/tenants/' . $e($tenantId) . '/profile/reviews';

// Render a 1–5 rating <select>, marking $selected.
$ratingSelect = static function (string $name, int $selected) use ($e): string {
    $out = '<select class="vb-input" name="' . $e($name) . '">';
    for ($i = 5; $i >= 1; $i--) {
        $sel = $i === $selected ? ' selected' : '';
        $out .= '<option value="' . $i . '"' . $sel . '>' . str_repeat('★', $i) . ' (' . $i . ')</option>';
    }
    return $out . '</select>';
};

// Normalise a stored reviewed_at DATETIME to a value for <input type="date">.
$dateValue = static function (mixed $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts === false ? '' : date('Y-m-d', $ts);
};
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title">Reviews</h2>
        <p class="vb-page-subtitle"><?= $e($tenant['name'] ?? '') ?></p>
    </div>
    <div>
        <a class="vb-btn vb-btn-secondary" href="/admin/tenants/<?= $e($tenantId) ?>/profile">
            <i data-lucide="arrow-left"></i>
            Back to profile
        </a>
    </div>
</div>

<!-- Add a review -->
<div class="vb-card">
    <div class="vb-card-body">
        <div class="vb-card-title">Add a review</div>
        <form method="post" action="<?= $base ?>">
            <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken) ?>">

            <div class="vb-form-group">
                <label class="vb-label">Rating</label>
                <?= $ratingSelect('rating', 5) ?>
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="rv-body">Review text</label>
                <textarea class="vb-input vb-textarea" id="rv-body" name="body" rows="4" required></textarea>
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="rv-name">Reviewer name</label>
                <input class="vb-input" id="rv-name" name="reviewer_name" type="text" maxlength="100"
                       placeholder="Leave blank to show “Anonymous”">
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="rv-date">Review date</label>
                <input class="vb-input" id="rv-date" name="reviewed_at" type="date">
                <span class="vb-settings-hint">Optional — defaults to today. Can be backdated for imported testimonials.</span>
            </div>

            <div class="vb-form-group">
                <label class="vb-label" for="rv-sort">Sort order</label>
                <input class="vb-input" id="rv-sort" name="sort_order" type="number" value="0" step="1">
                <span class="vb-settings-hint">Lower numbers appear first.</span>
            </div>

            <div class="vb-form-group">
                <label style="display:flex;align-items:center;gap:.5rem;">
                    <input type="checkbox" name="is_published" value="1">
                    Publish immediately
                </label>
                <span class="vb-settings-hint">Only published reviews appear on the public page.</span>
            </div>

            <button type="submit" class="vb-btn vb-btn-primary">
                <i data-lucide="plus"></i>
                Add review
            </button>
        </form>
    </div>
</div>

<!-- Existing reviews -->
<div class="vb-card" style="margin-top:1rem;">
    <div class="vb-card-body">
        <div class="vb-card-title"><?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?></div>

        <?php if ($reviews === []): ?>
        <p class="vb-settings-hint">No reviews yet. Add one above.</p>
        <?php else: ?>
        <?php foreach ($reviews as $r): ?>
            <?php
            $rid         = (string) ($r['id'] ?? '');
            $rRating     = max(1, min(5, (int) ($r['rating'] ?? 5)));
            $rBody       = (string) ($r['body'] ?? '');
            $rName       = (string) ($r['reviewer_name'] ?? '');
            $rPublished  = (int) ($r['is_published'] ?? 0) === 1;
            ?>
            <div style="border-top:1px solid var(--vb-border,#e5e7eb);padding:1rem 0;">
                <form method="post" action="<?= $base ?>/<?= $e($rid) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken) ?>">

                    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:.6rem;">
                        <span style="font-weight:600;">
                            <?= $rPublished
                                ? '<span style="color:#16a34a;">● Published</span>'
                                : '<span style="color:#9ca3af;">○ Hidden</span>' ?>
                        </span>
                        <span style="color:#f5a623;letter-spacing:.05em;"><?= str_repeat('★', $rRating) . str_repeat('☆', 5 - $rRating) ?></span>
                    </div>

                    <div class="vb-form-group">
                        <label class="vb-label">Rating</label>
                        <?= $ratingSelect('rating', $rRating) ?>
                    </div>

                    <div class="vb-form-group">
                        <label class="vb-label">Review text</label>
                        <textarea class="vb-input vb-textarea" name="body" rows="3" required><?= $e($rBody) ?></textarea>
                    </div>

                    <div class="vb-form-group">
                        <label class="vb-label">Reviewer name</label>
                        <input class="vb-input" name="reviewer_name" type="text" maxlength="100"
                               value="<?= $e($rName) ?>" placeholder="Anonymous">
                    </div>

                    <div class="vb-form-group">
                        <label class="vb-label">Review date</label>
                        <input class="vb-input" name="reviewed_at" type="date" value="<?= $e($dateValue($r['reviewed_at'] ?? '')) ?>">
                    </div>

                    <div class="vb-form-group">
                        <label class="vb-label">Sort order</label>
                        <input class="vb-input" name="sort_order" type="number" step="1" value="<?= $e((string) ($r['sort_order'] ?? 0)) ?>">
                    </div>

                    <button type="submit" class="vb-btn vb-btn-primary">
                        <i data-lucide="save"></i>
                        Save
                    </button>
                </form>

                <div style="display:flex;gap:.5rem;margin-top:.6rem;flex-wrap:wrap;">
                    <form method="post" action="<?= $base ?>/<?= $e($rid) ?>/publish">
                        <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken) ?>">
                        <button type="submit" class="vb-btn vb-btn-secondary">
                            <i data-lucide="<?= $rPublished ? 'eye-off' : 'eye' ?>"></i>
                            <?= $rPublished ? 'Hide' : 'Publish' ?>
                        </button>
                    </form>
                    <form method="post" action="<?= $base ?>/<?= $e($rid) ?>/delete"
                          onsubmit="return confirm('Delete this review permanently?');">
                        <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken) ?>">
                        <button type="submit" class="vb-btn vb-btn-danger">
                            <i data-lucide="trash-2"></i>
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
