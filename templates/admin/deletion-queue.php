<?php
/**
 * Operator deletion queue — GDPR Art. 17 request management.
 *
 * Shows pending deletion requests for operator review.
 * Operator can confirm (anonymize) or dismiss each request.
 *
 * Uses design system components: .vb-card, .vb-table, .vb-badge, .vb-btn.
 * Icons: Lucide via data-lucide.
 *
 * Variables: $pendingRequests, $processedRequests, $pageTitle
 */
use App\Engine\View;

$pageTitle = $pageTitle ?? __('admin.deletion.title');
$activePage = 'deletion-queue';
$csrfToken = \App\Middleware\CsrfMiddleware::generateToken();
$pendingRequests = $pendingRequests ?? [];
$processedRequests = $processedRequests ?? [];
$pendingCount = count($pendingRequests);

ob_start();
?>

<!-- Page Header -->
<div class="vb-page-header vb-animate-in stagger-1">
    <i data-lucide="shield" class="vb-page-header-icon"></i>
    <h2 class="vb-page-title">
        <?= __('admin.deletion.title') ?>
        <?php if ($pendingCount > 0): ?>
            <span class="vb-badge vb-badge-error"><?= $pendingCount ?></span>
        <?php else: ?>
            <span class="vb-badge vb-badge-success">0</span>
        <?php endif; ?>
    </h2>
</div>

<!-- Pending Requests -->
<div class="vb-card vb-animate-in stagger-2">
    <div class="vb-card-header">
        <div class="vb-card-title"><?= __('admin.deletion.pending_title') ?></div>
    </div>

    <?php if (empty($pendingRequests)): ?>
        <div class="vb-table-empty">
            <i data-lucide="shield-check" class="vb-table-empty-icon"></i>
            <div class="vb-table-empty-title"><?= __('admin.deletion.empty_title') ?></div>
            <div class="vb-table-empty-desc"><?= __('admin.deletion.empty_desc') ?></div>
        </div>
    <?php else: ?>
        <table class="vb-table">
            <thead>
                <tr>
                    <th><?= __('admin.deletion.th_customer') ?></th>
                    <th><?= __('admin.deletion.th_tenant') ?></th>
                    <th><?= __('admin.deletion.th_requested') ?></th>
                    <th><?= __('admin.deletion.th_bookings') ?></th>
                    <th style="text-align: right;"><?= __('admin.deletion.th_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingRequests as $i => $req): ?>
                <tr class="vb-animate-in stagger-<?= min($i + 3, 6) ?>">
                    <td>
                        <div style="font-weight: 600;"><?= View::e($req['name']) ?></div>
                        <div class="vb-mono" style="font-size: var(--vb-text-xs); color: var(--vb-text-tertiary);"><?= View::e($req['email']) ?></div>
                    </td>
                    <td>
                        <span style="font-size: var(--vb-text-xs); color: var(--vb-text-secondary);"><?= View::e($req['tenant_name']) ?></span>
                    </td>
                    <td>
                        <span class="vb-mono" style="font-size: var(--vb-text-xs); color: var(--vb-text-tertiary);"><?= View::e($req['deletion_requested_at']) ?></span>
                    </td>
                    <td>
                        <span class="vb-nums" style="font-size: var(--vb-text-xs);"><?= (int) ($req['booking_count'] ?? 0) ?></span>
                    </td>
                    <td>
                        <div class="vb-table-actions">
                            <!-- No-JS confirm pattern -->
                            <input type="checkbox" id="dq-confirm-<?= View::e($req['id']) ?>" class="vb-confirm-toggle">
                            <span class="vb-confirm-step-1">
                                <label for="dq-confirm-<?= View::e($req['id']) ?>" class="vb-btn vb-btn-danger vb-btn-sm" style="cursor: pointer;">
                                    <?= __('admin.deletion.btn_anonymize') ?>
                                </label>
                            </span>
                            <span class="vb-confirm-step-2">
                                <form method="post" action="/admin/deletion-queue/confirm" style="display: inline;">
                                    <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                                    <input type="hidden" name="customer_id" value="<?= View::e($req['id']) ?>">
                                    <button type="submit" class="vb-btn vb-btn-danger vb-btn-sm"><?= __('admin.deletion.btn_confirm') ?></button>
                                </form>
                                <label for="dq-confirm-<?= View::e($req['id']) ?>" class="vb-btn vb-btn-ghost vb-btn-sm" style="cursor: pointer;">
                                    <?= __('admin.deletion.btn_cancel') ?>
                                </label>
                            </span>

                            <form method="post" action="/admin/deletion-queue/dismiss" style="display: inline;">
                                <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                                <input type="hidden" name="customer_id" value="<?= View::e($req['id']) ?>">
                                <button type="submit" class="vb-btn vb-btn-secondary vb-btn-sm"><?= __('admin.deletion.btn_dismiss') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Recently Processed -->
<?php if (!empty($processedRequests)): ?>
<div class="vb-section-muted vb-animate-in stagger-5">
    <div class="vb-card">
        <div class="vb-card-header">
            <div class="vb-card-title"><?= __('admin.deletion.recently_processed') ?></div>
        </div>
        <table class="vb-table">
            <thead>
                <tr>
                    <th><?= __('admin.deletion.th_customer') ?></th>
                    <th><?= __('admin.deletion.th_tenant') ?></th>
                    <th><?= __('admin.deletion.th_requested') ?></th>
                    <th><?= __('admin.deletion.th_processed') ?></th>
                    <th><?= __('admin.deletion.th_status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processedRequests as $req): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600;"><?= View::e($req['name']) ?></div>
                        <div class="vb-mono" style="font-size: var(--vb-text-xs); color: var(--vb-text-tertiary);"><?= View::e($req['email']) ?></div>
                    </td>
                    <td><span style="font-size: var(--vb-text-xs); color: var(--vb-text-secondary);"><?= View::e($req['tenant_name']) ?></span></td>
                    <td><span class="vb-mono" style="font-size: var(--vb-text-xs); color: var(--vb-text-tertiary);"><?= View::e($req['deletion_requested_at']) ?></span></td>
                    <td><span class="vb-mono" style="font-size: var(--vb-text-xs); color: var(--vb-text-tertiary);"><?= View::e($req['anonymized_at'] ?? '') ?></span></td>
                    <td><span class="vb-badge vb-badge-success"><?= __('admin.deletion.status_anonymized') ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/admin/layout.php';
