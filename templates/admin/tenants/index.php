<?php
/**
 * Tenant list — operator-only view.
 *
 * Variables: $user, $version, $csrfToken, $tenants, $counts, $flash, $pageTitle, $activePage
 */
$activePage = 'tenants';

ob_start();
?>

<?php if ($flash): ?>
    <div class="vb-alert vb-alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?php if ($flash['type'] === 'success'): ?>
            <i data-lucide="check"></i>
        <?php else: ?>
            <i data-lucide="alert-circle"></i>
        <?php endif; ?>
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.tenants.title') ?></h2>
        <div class="vb-page-subtitle">
            <?= str_replace(':count', (string) ($counts['total'] ?? 0), __('admin.tenants.showing_count')) ?>
        </div>
    </div>
    <a href="/admin/tenants/create" class="vb-btn vb-btn-primary">
        <i data-lucide="plus"></i>
        <?= __('admin.tenants.create') ?>
    </a>
</div>

<?php if (empty($tenants)): ?>
    <div class="vb-empty vb-animate-in">
        <i data-lucide="building-2" class="vb-empty-icon"></i>
        <div class="vb-empty-title"><?= __('admin.tenants.empty_title') ?></div>
        <div class="vb-empty-desc"><?= __('admin.tenants.empty_desc') ?></div>
        <a href="/admin/tenants/create" class="vb-btn vb-btn-primary vb-btn-lg">
            <i data-lucide="plus"></i>
            <?= __('admin.tenants.create') ?>
        </a>
    </div>
<?php else: ?>
    <div class="vb-card">
        <div class="vb-table-wrap">
            <table class="vb-table">
                <thead>
                    <tr>
                        <th></th>
                        <th><?= __('admin.tenants.name') ?></th>
                        <th><?= __('admin.tenants.pattern') ?></th>
                        <th><?= __('admin.tenants.status') ?></th>
                        <th><?= __('admin.tenants.bookings') ?></th>
                        <th><?= __('admin.tenants.services') ?></th>
                        <th><?= __('admin.tenants.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $i => $tenant): ?>
                    <tr class="vb-fade-in-up stagger-<?= min($i + 1, 6) ?>">
                        <td>
                            <span class="vb-color-dot" style="background: <?= htmlspecialchars($tenant['brand_color'] ?? '#2563EB', ENT_QUOTES, 'UTF-8') ?>"></span>
                        </td>
                        <td>
                            <div class="vb-cell-name"><?= htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="vb-cell-detail">/book/<?= htmlspecialchars($tenant['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td>
                            <span class="vb-badge vb-badge-default"><?= htmlspecialchars(ucfirst($tenant['booking_pattern'] ?? 'timeslot'), ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <?php
                            $statusClass = match ($tenant['status']) {
                                'active'   => 'vb-badge-success',
                                'paused'   => 'vb-badge-warning',
                                'archived' => 'vb-badge-default',
                                default    => 'vb-badge-default',
                            };
                            ?>
                            <span class="vb-badge <?= $statusClass ?>">
                                <?= __('admin.tenants.status_' . ($tenant['status'] ?? 'active')) ?>
                            </span>
                        </td>
                        <td><?= (int) ($tenant['booking_count'] ?? 0) ?></td>
                        <td><?= (int) ($tenant['service_count'] ?? 0) ?></td>
                        <td>
                            <div class="vb-action-group">
                                <a href="/admin/tenants/<?= htmlspecialchars($tenant['id'], ENT_QUOTES, 'UTF-8') ?>/edit"
                                   class="vb-btn vb-btn-ghost vb-btn-sm"
                                   title="<?= __('admin.common.edit') ?>">
                                    <i data-lucide="pencil"></i>
                                </a>
                                <a href="/book/<?= htmlspecialchars($tenant['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                   target="_blank"
                                   rel="noopener"
                                   class="vb-btn vb-btn-ghost vb-btn-sm"
                                   title="<?= __('admin.tenants.view_booking_page') ?>">
                                    <i data-lucide="external-link"></i>
                                </a>
                                <?php if ($tenant['status'] !== 'archived'): ?>
                                    <form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenant['id'], ENT_QUOTES, 'UTF-8') ?>/archive" class="vb-inline-form">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="vb-btn vb-btn-ghost vb-btn-sm" title="<?= __('admin.tenants.archive') ?>">
                                            <i data-lucide="archive"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenant['id'], ENT_QUOTES, 'UTF-8') ?>/activate" class="vb-inline-form">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="vb-btn vb-btn-ghost vb-btn-sm" title="<?= __('admin.tenants.activate') ?>">
                                            <i data-lucide="rotate-ccw"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
