<?php

declare(strict_types=1);

/**
 * Perka platform Updates page (body only).
 *
 * Rendered by PerkaView::renderPath and injected as $content into the core
 * admin.layout by UpdatesController::shell().
 *
 * Variables: $pendingCount (int), $applied (list<array{migration,applied_at}>),
 *            $csrfToken
 */

use App\Perka\Shared\PerkaView;

$pendingCount = (int) ($pendingCount ?? 0);
$applied      = is_array($applied ?? null) ? $applied : [];
$csrfToken    = $csrfToken ?? '';

$e = static fn (mixed $v): string => PerkaView::e($v);
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title">Perka Updates</h2>
        <p class="vb-page-subtitle">Database migrations for Perka modules</p>
    </div>
</div>

<div class="vb-card">
    <div class="vb-card-body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <?php if ($pendingCount > 0): ?>
        <div>
            <strong><?= $e($pendingCount) ?> pending migration<?= $pendingCount === 1 ? '' : 's' ?></strong>
            <div class="vb-settings-hint">Run to apply. The run is guarded by an advisory lock, so it is safe if triggered twice.</div>
        </div>
        <form method="post" action="/admin/perka/updates/run">
            <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken) ?>">
            <button type="submit" class="vb-btn vb-btn-primary">
                <i data-lucide="download"></i>
                Run migrations
            </button>
        </form>
        <?php else: ?>
        <div>
            <strong>Up to date</strong>
            <div class="vb-settings-hint">No pending Perka migrations.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="vb-card" style="margin-top:1rem;">
    <div class="vb-card-body">
        <div class="vb-card-title">Applied migrations</div>
        <?php if ($applied === []): ?>
        <p class="vb-settings-hint">None applied yet.</p>
        <?php else: ?>
        <table class="vb-table" style="width:100%;">
            <thead>
                <tr>
                    <th style="text-align:left;">Migration</th>
                    <th style="text-align:left;">Applied at</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applied as $row): ?>
                <tr>
                    <td><code><?= $e($row['migration'] ?? '') ?></code></td>
                    <td><?= $e($row['applied_at'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
