<?php
/**
 * Audit Log Viewer — Compliance §5/§6 audit trail.
 *
 * Read-only view of structured audit log entries.
 * Filters: action type, time range.
 * Paginated, 50 entries per page.
 *
 * Variables: $user, $version, $csrfToken, $pageTitle, $entries, $total, $page, $perPage, $actionFilter
 */
$activePage = 'settings.audit';
$activeTab = 'audit';

/**
 * Action type → human-readable label + severity color mapping.
 */
$actionLabels = [
    'auth.login' => ['label' => 'Login', 'color' => 'var(--vb-admin-text-success)'],
    'auth.login_failed' => ['label' => 'Login failed', 'color' => 'var(--vb-admin-text-error)'],
    'auth.logout' => ['label' => 'Logout', 'color' => 'var(--vb-admin-text-secondary)'],
    'auth.password_changed' => ['label' => 'Password changed', 'color' => 'var(--vb-brand-primary)'],
    'auth.brute_force_detected' => ['label' => 'Brute force', 'color' => 'var(--vb-admin-text-error)'],
    'auth.access_denied' => ['label' => 'Access denied', 'color' => 'var(--vb-admin-text-error)'],
    'settings.updated' => ['label' => 'Settings changed', 'color' => 'var(--vb-brand-primary)'],
    'booking.created' => ['label' => 'Booking created', 'color' => 'var(--vb-admin-text-success)'],
    'booking.cancelled' => ['label' => 'Booking cancelled', 'color' => 'var(--vb-admin-text-error)'],
    'booking.rescheduled' => ['label' => 'Booking rescheduled', 'color' => 'var(--vb-brand-primary)'],
    'booking.status_changed' => ['label' => 'Status changed', 'color' => 'var(--vb-brand-primary)'],
    'customer.anonymized' => ['label' => 'Customer anonymized', 'color' => 'var(--vb-admin-text-warning, #f59e0b)'],
    'privacy.request_received' => ['label' => 'Privacy request', 'color' => 'var(--vb-admin-text-warning, #f59e0b)'],
    'privacy.request_completed' => ['label' => 'Privacy completed', 'color' => 'var(--vb-admin-text-success)'],
    'data.export_generated' => ['label' => 'Data export', 'color' => 'var(--vb-brand-primary)'],
    'api_key.created' => ['label' => 'API key created', 'color' => 'var(--vb-admin-text-warning, #f59e0b)'],
    'api_key.revoked' => ['label' => 'API key revoked', 'color' => 'var(--vb-admin-text-secondary)'],
    'system.audit_cleanup' => ['label' => 'Audit cleanup', 'color' => 'var(--vb-admin-text-secondary)'],
    'system.migration' => ['label' => 'Migration', 'color' => 'var(--vb-admin-text-secondary)'],
    'retention.executed' => ['label' => 'Retention job', 'color' => 'var(--vb-admin-text-secondary)'],
    'role.changed' => ['label' => 'Role changed', 'color' => 'var(--vb-admin-text-warning, #f59e0b)'],
    'rate_limit.exceeded' => ['label' => 'Rate limited', 'color' => 'var(--vb-admin-text-error)'],
    'tenant.created' => ['label' => 'Tenant created', 'color' => 'var(--vb-admin-text-success)'],
    'tenant.archived' => ['label' => 'Tenant archived', 'color' => 'var(--vb-admin-text-secondary)'],
];

$totalPages = max(1, (int) ceil($total / $perPage));

ob_start();

include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>

<div class="vb-card vb-fade-in-up stagger-1">
    <div class="vb-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div class="vb-card-title">Audit Log</div>
            <div class="vb-card-desc"><?= number_format($total) ?> entries · Structured event log for accountability and compliance</div>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <form method="GET" action="/admin/settings/audit" style="display: flex; gap: 0.5rem; align-items: center;">
                <select name="action" class="vb-input" style="width: auto; min-width: 160px; font-size: var(--vb-text-xs);" onchange="this.form.submit()">
                    <option value="">All events</option>
                    <?php
                    $uniqueActions = array_unique(array_column($entries, 'action'));
                    // Use known labels for ordering, then any unknown actions
                    foreach ($actionLabels as $actionKey => $meta):
                        $selected = ($actionFilter ?? '') === $actionKey ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($actionKey, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if (empty($entries)): ?>
        <div style="text-align: center; padding: 3rem 2rem; color: var(--vb-admin-text-tertiary);">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 1rem; opacity: 0.4;">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <div style="font-size: var(--vb-text-sm); font-weight: 500;">No audit log entries<?= ($actionFilter ?? '') !== '' ? ' matching this filter' : '' ?></div>
            <div style="font-size: var(--vb-text-xs); margin-top: 0.375rem; max-width: 24rem; margin-left: auto; margin-right: auto;">
                Audit entries are created automatically when actions like logins, settings changes, and booking operations occur.
            </div>
        </div>
    <?php else: ?>
        <div class="vb-audit-table-wrap" style="overflow-x: auto;">
            <table class="vb-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 10rem;">Time</th>
                        <th style="width: 9rem;">Event</th>
                        <th style="width: 7rem;">Actor</th>
                        <th style="width: 6rem;">Entity</th>
                        <th>Details</th>
                        <th style="width: 5.5rem;">Request</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <?php
                        $meta = $actionLabels[$entry['action']] ?? ['label' => $entry['action'], 'color' => 'var(--vb-admin-text-secondary)'];
                        $details = $entry['details'] ? json_decode($entry['details'], true) : null;
                        $createdAt = new \DateTime($entry['created_at']);
                        ?>
                        <tr class="vb-audit-row">
                            <td style="font-size: var(--vb-text-xs); color: var(--vb-admin-text-secondary); font-family: var(--vb-font-mono); white-space: nowrap;">
                                <?= $createdAt->format('M d, H:i:s') ?>
                            </td>
                            <td>
                                <span class="vb-audit-badge" style="
                                    display: inline-flex; align-items: center; gap: 0.375rem;
                                    font-size: var(--vb-text-xs); font-weight: 500;
                                    padding: 0.125rem 0.5rem;
                                    border-radius: var(--vb-radius-full);
                                    background: color-mix(in srgb, <?= $meta['color'] ?> 12%, transparent);
                                    color: <?= $meta['color'] ?>;
                                    white-space: nowrap;
                                ">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0;"></span>
                                    <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="font-size: var(--vb-text-xs); color: var(--vb-admin-text-secondary);">
                                <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                    <?php if ($entry['actor_type'] === 'operator'): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                    <?php elseif ($entry['actor_type'] === 'business_user'): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <?php elseif ($entry['actor_type'] === 'system'): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h.01"/><path d="M17 7h.01"/><path d="M7 17h.01"/><path d="M17 17h.01"/></svg>
                                    <?php elseif ($entry['actor_type'] === 'api'): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 16 4-4-4-4"/><path d="m6 8-4 4 4 4"/><path d="m14.5 4-5 16"/></svg>
                                    <?php else: ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                    <?php endif; ?>
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $entry['actor_type'])), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="font-size: var(--vb-text-xs); color: var(--vb-admin-text-secondary);">
                                <?= htmlspecialchars($entry['entity_type'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($entry['entity_id']): ?>
                                    <br><span style="font-family: var(--vb-font-mono); font-size: 0.625rem; color: var(--vb-admin-text-tertiary);"><?= htmlspecialchars(substr($entry['entity_id'], 0, 8), ENT_QUOTES, 'UTF-8') ?>…</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: var(--vb-text-xs); color: var(--vb-admin-text-secondary); max-width: 24rem; overflow: hidden; text-overflow: ellipsis;">
                                <?php if ($details): ?>
                                    <?php foreach ($details as $key => $value): ?>
                                        <?php if (is_array($value) && isset($value['old'], $value['new'])): ?>
                                            <span style="display: inline-block; margin-right: 0.5rem; margin-bottom: 0.125rem;">
                                                <span style="font-weight: 500; color: var(--vb-admin-text-primary);"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>:</span>
                                                <span style="text-decoration: line-through; opacity: 0.5;"><?= htmlspecialchars((string)$value['old'], ENT_QUOTES, 'UTF-8') ?></span>
                                                → <?= htmlspecialchars((string)$value['new'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php elseif (is_array($value)): ?>
                                            <span style="font-weight: 500; color: var(--vb-admin-text-primary);"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>:</span>
                                            <span style="font-family: var(--vb-font-mono);"><?= htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span style="display: inline-block; margin-right: 0.5rem; margin-bottom: 0.125rem;">
                                                <span style="font-weight: 500; color: var(--vb-admin-text-primary);"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>:</span>
                                                <?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--vb-admin-text-tertiary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family: var(--vb-font-mono); font-size: 0.625rem; color: var(--vb-admin-text-tertiary); white-space: nowrap;">
                                <?= htmlspecialchars(substr($entry['request_id'], 0, 8), ENT_QUOTES, 'UTF-8') ?>…
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem 0.75rem; border-top: 1px solid var(--vb-admin-border); font-size: var(--vb-text-xs); color: var(--vb-admin-text-secondary);">
                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                <div style="display: flex; gap: 0.375rem;">
                    <?php if ($page > 1): ?>
                        <a href="/admin/settings/audit?page=<?= $page - 1 ?><?= ($actionFilter ?? '') !== '' ? '&action=' . urlencode($actionFilter) : '' ?>" class="vb-btn vb-btn-ghost" style="padding: 0.25rem 0.75rem; font-size: var(--vb-text-xs);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="/admin/settings/audit?page=<?= $page + 1 ?><?= ($actionFilter ?? '') !== '' ? '&action=' . urlencode($actionFilter) : '' ?>" class="vb-btn vb-btn-ghost" style="padding: 0.25rem 0.75rem; font-size: var(--vb-text-xs);">
                            Next
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    .vb-table th {
        font-size: var(--vb-text-xs);
        font-weight: 500;
        color: var(--vb-admin-text-secondary);
        text-align: left;
        padding: 0.625rem 1rem;
        border-bottom: 1px solid var(--vb-admin-border);
        white-space: nowrap;
    }
    .vb-table td {
        padding: 0.5rem 1rem;
        border-bottom: 1px solid color-mix(in srgb, var(--vb-admin-border) 50%, transparent);
        vertical-align: top;
    }
    .vb-audit-row {
        transition: background 0.15s ease;
    }
    .vb-audit-row:hover {
        background: color-mix(in srgb, var(--vb-brand-primary) 3%, transparent);
    }
    .vb-audit-row:last-child td {
        border-bottom: none;
    }
</style>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
