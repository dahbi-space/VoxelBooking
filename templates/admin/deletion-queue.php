<?php
/**
 * Operator deletion queue — GDPR Art. 17 request management.
 *
 * Shows pending deletion requests for operator review.
 * Operator can confirm (anonymize) or dismiss each request.
 *
 * Variables: $pendingRequests, $processedRequests, $pageTitle
 */
use App\Engine\View;

$pageTitle = $pageTitle ?? 'Deletion Queue';
$activePage = 'deletion-queue';
$csrfToken = \App\Middleware\CsrfMiddleware::generateToken();
$pendingRequests = $pendingRequests ?? [];
$processedRequests = $processedRequests ?? [];

ob_start();
?>

<style>
    .dq-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }

    .dq-header h1 {
        font-size: var(--vb-text-xl);
        font-weight: 700;
        letter-spacing: var(--vb-tracking-tight);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .dq-header h1 svg {
        width: 24px;
        height: 24px;
        color: var(--vb-admin-error);
    }

    .dq-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.5rem;
        height: 1.5rem;
        padding: 0 0.5rem;
        border-radius: var(--vb-radius-full);
        font-size: var(--vb-text-xs);
        font-weight: 700;
    }

    .dq-badge-pending {
        background: var(--vb-admin-error-bg);
        color: var(--vb-admin-error);
    }

    .dq-badge-clear {
        background: var(--vb-admin-success-bg);
        color: var(--vb-admin-success);
    }

    .dq-section-title {
        font-size: var(--vb-text-md);
        font-weight: 600;
        letter-spacing: var(--vb-tracking-tight);
        margin-bottom: 1rem;
        color: var(--vb-admin-text-secondary);
    }

    .dq-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: var(--vb-text-sm);
    }

    .dq-table thead {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .dq-table th {
        background: var(--vb-admin-bg-surface);
        padding: 0.625rem 0.75rem;
        text-align: left;
        font-weight: 600;
        font-size: var(--vb-text-xs);
        text-transform: uppercase;
        letter-spacing: var(--vb-tracking-wide);
        color: var(--vb-admin-text-tertiary);
        border-bottom: 1px solid var(--vb-admin-border-subtle);
    }

    .dq-table td {
        padding: 0.75rem;
        border-bottom: 1px solid var(--vb-admin-border-subtle);
        vertical-align: middle;
    }

    .dq-table tr:last-child td {
        border-bottom: none;
    }

    .dq-table tr:hover td {
        background: var(--vb-admin-bg-hover);
    }

    .dq-customer-name {
        font-weight: 600;
        color: var(--vb-admin-text-primary);
    }

    .dq-customer-email {
        font-size: var(--vb-text-xs);
        color: var(--vb-admin-text-tertiary);
    }

    .dq-tenant {
        font-size: var(--vb-text-xs);
        color: var(--vb-admin-text-secondary);
    }

    .dq-timestamp {
        font-size: var(--vb-text-xs);
        color: var(--vb-admin-text-tertiary);
        white-space: nowrap;
    }

    .dq-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .dq-actions form {
        display: inline;
    }

    .dq-btn-confirm {
        padding: 0.375rem 0.75rem;
        border-radius: var(--vb-radius-md);
        font-size: var(--vb-text-xs);
        font-weight: 600;
        cursor: pointer;
        border: none;
        background: var(--vb-admin-error-bg);
        color: var(--vb-admin-error);
        transition: all var(--vb-duration-fast) var(--vb-ease-out);
    }

    .dq-btn-confirm:hover {
        background: var(--vb-admin-error);
        color: #fff;
    }

    .dq-btn-dismiss {
        padding: 0.375rem 0.75rem;
        border-radius: var(--vb-radius-md);
        font-size: var(--vb-text-xs);
        font-weight: 600;
        cursor: pointer;
        border: 1px solid var(--vb-admin-border-subtle);
        background: transparent;
        color: var(--vb-admin-text-secondary);
        transition: all var(--vb-duration-fast) var(--vb-ease-out);
    }

    .dq-btn-dismiss:hover {
        border-color: var(--vb-admin-text-tertiary);
        color: var(--vb-admin-text-primary);
    }

    .dq-empty {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--vb-admin-text-tertiary);
    }

    .dq-empty svg {
        width: 48px;
        height: 48px;
        margin-bottom: 1rem;
        opacity: 0.4;
    }

    .dq-empty-title {
        font-weight: 600;
        font-size: var(--vb-text-md);
        color: var(--vb-admin-text-secondary);
        margin-bottom: 0.25rem;
    }

    .dq-processed-section {
        margin-top: 2rem;
        opacity: 0.7;
    }

    .dq-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        border-radius: var(--vb-radius-full);
        font-size: var(--vb-text-2xs);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: var(--vb-tracking-wide);
        background: var(--vb-admin-success-bg);
        color: var(--vb-admin-success);
    }

    /* No-JS confirm pattern for deletion */
    .dq-confirm-toggle { display: none; }
    .dq-confirm-step-2 { display: none; }
    .dq-confirm-step-1 { display: inline; }
    .dq-confirm-toggle:checked ~ .dq-confirm-step-1 { display: none; }
    .dq-confirm-toggle:checked ~ .dq-confirm-step-2 { display: inline-flex; gap: 0.375rem; align-items: center; }

    @media (max-width: 768px) {
        .dq-table thead { display: none; }
        .dq-table tr { display: block; padding: 0.75rem; border-bottom: 1px solid var(--vb-admin-border-subtle); }
        .dq-table td { display: block; padding: 0.25rem 0; border: none; }
        .dq-actions { margin-top: 0.5rem; }
    }
</style>

<div class="vb-fade-in-up stagger-1">
    <div class="dq-header">
        <h1>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Deletion Queue
            <?php if (count($pendingRequests) > 0): ?>
                <span class="dq-badge dq-badge-pending"><?= count($pendingRequests) ?></span>
            <?php else: ?>
                <span class="dq-badge dq-badge-clear">0</span>
            <?php endif; ?>
        </h1>
    </div>
</div>

<!-- Pending Requests -->
<div class="vb-card vb-fade-in-up stagger-2">
    <h2 class="dq-section-title">Pending Requests</h2>

    <?php if (empty($pendingRequests)): ?>
        <div class="dq-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <polyline points="9 12 11 14 15 10"/>
            </svg>
            <div class="dq-empty-title">No pending requests</div>
            <p style="font-size: var(--vb-text-sm);">All customer deletion requests have been processed.</p>
        </div>
    <?php else: ?>
        <table class="dq-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Tenant</th>
                    <th>Requested</th>
                    <th>Bookings</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingRequests as $i => $req): ?>
                <tr class="vb-fade-in-up stagger-<?= min($i + 3, 8) ?>">
                    <td>
                        <div class="dq-customer-name"><?= View::e($req['name']) ?></div>
                        <div class="dq-customer-email"><?= View::e($req['email']) ?></div>
                    </td>
                    <td>
                        <span class="dq-tenant"><?= View::e($req['tenant_name']) ?></span>
                    </td>
                    <td>
                        <span class="dq-timestamp"><?= View::e($req['deletion_requested_at']) ?></span>
                    </td>
                    <td>
                        <span class="dq-timestamp"><?= (int) ($req['booking_count'] ?? 0) ?></span>
                    </td>
                    <td>
                        <div class="dq-actions" style="justify-content: flex-end;">
                            <!-- No-JS confirm pattern -->
                            <input type="checkbox" id="dq-confirm-<?= View::e($req['id']) ?>" class="dq-confirm-toggle">
                            <span class="dq-confirm-step-1">
                                <label for="dq-confirm-<?= View::e($req['id']) ?>" class="dq-btn-confirm" style="cursor: pointer;">
                                    Anonymize
                                </label>
                            </span>
                            <span class="dq-confirm-step-2">
                                <form method="post" action="/admin/deletion-queue/confirm">
                                    <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                                    <input type="hidden" name="customer_id" value="<?= View::e($req['id']) ?>">
                                    <button type="submit" class="dq-btn-confirm">Confirm</button>
                                </form>
                                <label for="dq-confirm-<?= View::e($req['id']) ?>" class="dq-btn-dismiss" style="cursor: pointer;">
                                    Cancel
                                </label>
                            </span>

                            <form method="post" action="/admin/deletion-queue/dismiss">
                                <input type="hidden" name="_csrf_token" value="<?= View::e($csrfToken) ?>">
                                <input type="hidden" name="customer_id" value="<?= View::e($req['id']) ?>">
                                <button type="submit" class="dq-btn-dismiss">Dismiss</button>
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
<div class="dq-processed-section vb-fade-in-up stagger-6">
    <div class="vb-card">
        <h2 class="dq-section-title">Recently Processed</h2>
        <table class="dq-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Tenant</th>
                    <th>Requested</th>
                    <th>Processed</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processedRequests as $req): ?>
                <tr>
                    <td>
                        <div class="dq-customer-name"><?= View::e($req['name']) ?></div>
                        <div class="dq-customer-email"><?= View::e($req['email']) ?></div>
                    </td>
                    <td><span class="dq-tenant"><?= View::e($req['tenant_name']) ?></span></td>
                    <td><span class="dq-timestamp"><?= View::e($req['deletion_requested_at']) ?></span></td>
                    <td><span class="dq-timestamp"><?= View::e($req['anonymized_at'] ?? '') ?></span></td>
                    <td><span class="dq-status-badge">Anonymized</span></td>
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
