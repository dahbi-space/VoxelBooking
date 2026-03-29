<?php
/**
 * Customer list — tenant-scoped.
 *
 * Variables: $tenant, $customers, $total, $page, $perPage, $search, $tenantId, $csrfToken
 */
$tenant = $tenant ?? [];
$customers = $customers ?? [];
$tenantId = $tenantId ?? '';
$search = $search ?? '';
$total = $total ?? 0;
$page = $page ?? 1;
$perPage = $perPage ?? 25;
$totalPages = max(1, (int) ceil($total / $perPage));

ob_start();
?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title"><?= __('admin.customers.page_title') ?></h2>
        <p class="vb-page-subtitle">
            <?= str_replace(':count', (string) $total, __('admin.customers.showing_count')) ?>
        </p>
    </div>
</div>

<!-- Search -->
<div class="vb-card vb-filter-bar">
    <form method="GET" action="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/customers" class="vb-filter-form">
        <div class="vb-search-input-wrapper">
            <i data-lucide="search" class="vb-search-icon"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                   class="vb-input vb-search-input" placeholder="<?= __('admin.customers.search_placeholder') ?>"
                   id="customer-search">
        </div>
        <button type="submit" class="vb-btn vb-btn-ghost vb-btn-sm">
            <i data-lucide="search" style="width: 14px; height: 14px;"></i>
            <?= __('admin.common.search') ?>
        </button>
        <?php if ($search !== ''): ?>
            <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/customers" class="vb-btn vb-btn-ghost vb-btn-sm">
                <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                <?= __('admin.common.clear') ?>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($customers)): ?>
    <div class="vb-empty-state vb-animate-in">
        <i data-lucide="users" class="vb-empty-icon"></i>
        <?php if ($search !== ''): ?>
            <h3><?= __('admin.customers.empty_search_title') ?></h3>
            <p><?= __('admin.customers.empty_search_desc') ?></p>
        <?php else: ?>
            <h3><?= __('admin.customers.empty_title') ?></h3>
            <p><?= __('admin.customers.empty_desc') ?></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="vb-card">
        <div class="vb-table-wrapper">
            <table class="vb-table" id="customers-table">
                <thead>
                    <tr>
                        <th><?= __('admin.customers.col_name') ?></th>
                        <th><?= __('admin.customers.col_email') ?></th>
                        <th><?= __('admin.customers.col_phone') ?></th>
                        <th class="vb-text-center"><?= __('admin.customers.col_bookings') ?></th>
                        <th><?= __('admin.customers.col_last_booking') ?></th>
                        <th><?= __('admin.customers.col_joined') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $i => $c): ?>
                    <tr class="vb-fade-in-up stagger-<?= min($i + 1, 6) ?> vb-clickable-row"
                        onclick="window.location='/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/customers/<?= htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8') ?>'">
                        <td>
                            <div class="vb-user-cell">
                                <span class="vb-avatar vb-avatar-xs"><?= mb_strtoupper(mb_substr($c['name'], 0, 1)) ?></span>
                                <span class="vb-cell-name"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </td>
                        <td class="vb-text-secondary"><?= htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="vb-text-secondary">
                            <?php if ($c['phone']): ?>
                                <?= htmlspecialchars($c['phone'], ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="vb-text-tertiary">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="vb-text-center">
                            <span class="vb-badge vb-badge-neutral"><?= (int) $c['booking_count'] ?></span>
                        </td>
                        <td class="vb-text-secondary">
                            <?php if ($c['last_booking_at']): ?>
                                <?= htmlspecialchars(date('M j, Y', strtotime($c['last_booking_at'])), ENT_QUOTES, 'UTF-8') ?>
                            <?php else: ?>
                                <span class="vb-text-tertiary">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="vb-text-secondary">
                            <?= htmlspecialchars(date('M j, Y', strtotime($c['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="vb-pagination">
        <?php if ($page > 1): ?>
            <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/customers?page=<?= $page - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
               class="vb-btn vb-btn-ghost vb-btn-sm">
                <i data-lucide="chevron-left" style="width: 14px; height: 14px;"></i>
                <?= __('admin.common.previous') ?>
            </a>
        <?php endif; ?>
        <span class="vb-pagination-info">
            <?= str_replace([':page', ':total'], [(string) $page, (string) $totalPages], __('admin.common.page_of')) ?>
        </span>
        <?php if ($page < $totalPages): ?>
            <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/customers?page=<?= $page + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
               class="vb-btn vb-btn-ghost vb-btn-sm">
                <?= __('admin.common.next') ?>
                <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 3) . '/admin/layout.php';
