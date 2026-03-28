<?php
/**
 * Audit Log Viewer — Compliance §5/§6 audit trail.
 *
 * Read-only view of structured audit log entries.
 * Filters: action type. Paginated, 50 entries per page.
 *
 * Uses design system: .vb-card, .vb-table, .vb-badge, .vb-btn, .vb-pagination.
 * Icons: Lucide via data-lucide (all inline SVGs removed).
 *
 * Variables: $user, $version, $csrfToken, $pageTitle, $entries, $total, $page, $perPage, $actionFilter
 */
$activePage = 'settings.audit';
$activeTab = 'audit';

/** Action type → label + badge semantic variant. */
$actionLabels = [
    'auth.login'                => ['label' => __('admin.audit.action_auth_login'),             'variant' => 'success'],
    'auth.login_failed'         => ['label' => __('admin.audit.action_auth_login_failed'),      'variant' => 'error'],
    'auth.logout'               => ['label' => __('admin.audit.action_auth_logout'),             'variant' => 'default'],
    'auth.password_changed'     => ['label' => __('admin.audit.action_auth_password_changed'),   'variant' => 'accent'],
    'auth.brute_force_detected' => ['label' => __('admin.audit.action_auth_brute_force'),        'variant' => 'error'],
    'auth.access_denied'        => ['label' => __('admin.audit.action_auth_access_denied'),      'variant' => 'error'],
    'settings.updated'          => ['label' => __('admin.audit.action_settings_updated'),         'variant' => 'accent'],
    'booking.created'           => ['label' => __('admin.audit.action_booking_created'),          'variant' => 'success'],
    'booking.cancelled'         => ['label' => __('admin.audit.action_booking_cancelled'),        'variant' => 'error'],
    'booking.rescheduled'       => ['label' => __('admin.audit.action_booking_rescheduled'),      'variant' => 'accent'],
    'booking.status_changed'    => ['label' => __('admin.audit.action_booking_status_changed'),   'variant' => 'accent'],
    'customer.anonymized'       => ['label' => __('admin.audit.action_customer_anonymized'),      'variant' => 'warning'],
    'privacy.request_received'  => ['label' => __('admin.audit.action_privacy_request_received'), 'variant' => 'warning'],
    'privacy.request_completed' => ['label' => __('admin.audit.action_privacy_request_completed'),'variant' => 'success'],
    'privacy.deletion_confirmed'=> ['label' => __('admin.audit.action_privacy_deletion_confirmed') ?? 'Deletion confirmed', 'variant' => 'warning'],
    'privacy.deletion_dismissed'=> ['label' => __('admin.audit.action_privacy_deletion_dismissed') ?? 'Deletion dismissed', 'variant' => 'default'],
    'data.export_generated'     => ['label' => __('admin.audit.action_data_export'),             'variant' => 'accent'],
    'api_key.created'           => ['label' => __('admin.audit.action_api_key_created'),         'variant' => 'warning'],
    'api_key.revoked'           => ['label' => __('admin.audit.action_api_key_revoked'),         'variant' => 'default'],
    'system.audit_cleanup'      => ['label' => __('admin.audit.action_system_audit_cleanup'),     'variant' => 'default'],
    'system.migration'          => ['label' => __('admin.audit.action_system_migration'),         'variant' => 'default'],
    'retention.executed'        => ['label' => __('admin.audit.action_retention_executed'),       'variant' => 'default'],
    'role.changed'              => ['label' => __('admin.audit.action_role_changed'),             'variant' => 'warning'],
    'rate_limit.exceeded'       => ['label' => __('admin.audit.action_rate_limit_exceeded'),     'variant' => 'error'],
    'tenant.created'            => ['label' => __('admin.audit.action_tenant_created'),           'variant' => 'success'],
    'tenant.archived'           => ['label' => __('admin.audit.action_tenant_archived'),         'variant' => 'default'],
];

/** Actor type → Lucide icon name. */
$actorIcons = [
    'operator'      => 'shield',
    'business_user' => 'user',
    'system'        => 'server',
    'api'           => 'zap',
    'customer'      => 'user',
];

$totalPages = max(1, (int) ceil($total / $perPage));

ob_start();

include dirname(__DIR__, 2) . '/partials/settings-tabs.php';
?>

<div class="vb-card vb-animate-in stagger-1">
    <div class="vb-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <div class="vb-card-title"><?= __('admin.audit.title') ?></div>
            <div class="vb-card-desc"><?= __n($total) ?> <?= __('admin.audit.desc_suffix') ?></div>
        </div>
        <form method="GET" action="/admin/settings/audit" style="display: flex; gap: 0.5rem; align-items: center;">
            <select name="action" class="vb-input vb-input-compact" style="width: auto; min-width: 160px;" onchange="this.form.submit()">
                <option value=""><?= __('admin.audit.all_events') ?></option>
                <?php foreach ($actionLabels as $actionKey => $meta):
                    $selected = ($actionFilter ?? '') === $actionKey ? 'selected' : '';
                ?>
                    <option value="<?= htmlspecialchars($actionKey, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($entries)): ?>
        <div class="vb-table-empty">
            <i data-lucide="shield-check" class="vb-table-empty-icon"></i>
            <div class="vb-table-empty-title">
                <?= __('admin.audit.empty_title') ?><?= ($actionFilter ?? '') !== '' ? ' ' . __('admin.audit.empty_filter') : '' ?>
            </div>
            <div class="vb-table-empty-desc"><?= __('admin.audit.empty_desc') ?></div>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="vb-table">
                <thead>
                    <tr>
                        <th style="width: 10rem;"><?= __('admin.audit.th_time') ?></th>
                        <th style="width: 9rem;"><?= __('admin.audit.th_event') ?></th>
                        <th style="width: 6rem;"><?= __('admin.audit.th_actor') ?></th>
                        <th style="width: 5rem;"><?= __('admin.audit.th_entity') ?></th>
                        <th><?= __('admin.audit.th_details') ?></th>
                        <th style="width: 5rem;"><?= __('admin.audit.th_request') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <?php
                        $meta = $actionLabels[$entry['action']] ?? ['label' => $entry['action'], 'variant' => 'default'];
                        $details = $entry['details'] ? json_decode($entry['details'], true) : null;
                        $createdAt = new \DateTime($entry['created_at']);
                        $actorIcon = $actorIcons[$entry['actor_type']] ?? 'help-circle';
                        ?>
                        <tr>
                            <td>
                                <span class="vb-mono" style="font-size: var(--vb-text-xs); color: var(--vb-text-secondary);">
                                    <?= \App\Engine\Locale::datetimeFull($createdAt) ?>
                                </span>
                            </td>
                            <td>
                                <span class="vb-badge vb-badge-<?= $meta['variant'] ?>">
                                    <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="font-size: var(--vb-text-xs); color: var(--vb-text-secondary);">
                                <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                    <i data-lucide="<?= $actorIcon ?>" style="width: 13px; height: 13px;"></i>
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $entry['actor_type'])), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="font-size: var(--vb-text-xs); color: var(--vb-text-secondary);">
                                <?= htmlspecialchars($entry['entity_type'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($entry['entity_id']): ?>
                                    <br><span class="vb-mono" style="font-size: 0.625rem; color: var(--vb-text-tertiary);"><?= htmlspecialchars(substr($entry['entity_id'], 0, 8), ENT_QUOTES, 'UTF-8') ?>…</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: var(--vb-text-xs); color: var(--vb-text-secondary); max-width: 24rem; overflow: hidden; text-overflow: ellipsis;">
                                <?php if ($details): ?>
                                    <?php foreach ($details as $key => $value): ?>
                                        <?php if (is_array($value) && isset($value['old'], $value['new'])): ?>
                                            <span style="display: inline-block; margin-right: 0.375rem; margin-bottom: 0.125rem;">
                                                <strong style="color: var(--vb-text-primary);"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>:</strong>
                                                <span style="text-decoration: line-through; opacity: 0.5;"><?= htmlspecialchars((string)$value['old'], ENT_QUOTES, 'UTF-8') ?></span>
                                                → <?= htmlspecialchars((string)$value['new'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php elseif (is_array($value)): ?>
                                            <strong style="color: var(--vb-text-primary);"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>:</strong>
                                            <span class="vb-mono"><?= htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span style="display: inline-block; margin-right: 0.375rem; margin-bottom: 0.125rem;">
                                                <strong style="color: var(--vb-text-primary);"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>:</strong>
                                                <?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--vb-text-tertiary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="vb-mono" style="font-size: 0.625rem; color: var(--vb-text-tertiary);">
                                    <?= htmlspecialchars(substr($entry['request_id'], 0, 8), ENT_QUOTES, 'UTF-8') ?>…
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="vb-pagination">
                <span><?= str_replace([':page', ':total'], [$page, $totalPages], __('admin.audit.page_of')) ?></span>
                <div class="vb-pagination-nav">
                    <?php if ($page > 1): ?>
                        <a href="/admin/settings/audit?page=<?= $page - 1 ?><?= ($actionFilter ?? '') !== '' ? '&action=' . urlencode($actionFilter) : '' ?>" class="vb-btn vb-btn-ghost vb-btn-sm">
                            <i data-lucide="chevron-left"></i>
                            <?= __('admin.audit.previous') ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="/admin/settings/audit?page=<?= $page + 1 ?><?= ($actionFilter ?? '') !== '' ? '&action=' . urlencode($actionFilter) : '' ?>" class="vb-btn vb-btn-ghost vb-btn-sm">
                            <?= __('admin.audit.next') ?>
                            <i data-lucide="chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
