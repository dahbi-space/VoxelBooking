<?php
/**
 * Customer list — tenant-scoped.
 *
 * Follows the canonical table-page structure from tenants/index.php:
 *   1. vb-page-header  (title + subtitle + optional action)
 *   2. vb-table-container  (single card wrapping toolbar + table)
 *        ├ vb-table-toolbar  (search + filters)
 *        └ vb-table-wrap > table.vb-table
 *   3. vb-pagination  (outside the card)
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
$baseUrl = "/admin/tenants/" . htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8');

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

<?php if (empty($customers) && $search === ''): ?>
    <div class="vb-empty-state vb-animate-in">
        <i data-lucide="users" class="vb-empty-icon"></i>
        <h3><?= __('admin.customers.empty_title') ?></h3>
        <p><?= __('admin.customers.empty_desc') ?></p>
    </div>
<?php else: ?>
    <div class="vb-table-container vb-animate-in">
        <!-- Toolbar: search -->
        <div class="vb-table-toolbar">
            <form action="<?= $baseUrl ?>/customers" method="GET" class="vb-table-search">
                <i data-lucide="search" class="vb-table-search-icon"></i>
                <input type="text"
                       name="search"
                       value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= __('admin.customers.search_placeholder') ?>"
                       class="vb-table-search-input"
                       id="customer-search" />
            </form>
            <?php if ($search !== ''): ?>
            <div class="vb-table-toolbar-right">
                <a href="<?= $baseUrl ?>/customers" class="vb-btn vb-btn-ghost vb-btn-sm">
                    <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                    <?= __('admin.common.clear') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($customers)): ?>
            <!-- Search returned zero results -->
            <div class="vb-empty-state vb-empty-state--compact">
                <i data-lucide="search-x" class="vb-empty-icon"></i>
                <h3><?= __('admin.customers.empty_search_title') ?></h3>
                <p><?= __('admin.customers.empty_search_desc') ?></p>
            </div>
        <?php else: ?>
            <div class="vb-table-wrap">
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
                            data-href="<?= $baseUrl ?>/customers/<?= htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8') ?>">
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
                                <?php $bc = (int) $c['booking_count']; ?>
                                <?php if ($bc > 0): ?>
                                    <span class="vb-cell-numeric"><?= $bc ?></span>
                                <?php else: ?>
                                    <span class="vb-cell-numeric vb-text-tertiary">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="vb-text-secondary">
                                <?php if ($c['last_booking_at']): ?>
                                    <?= htmlspecialchars(\App\Engine\Locale::dateLong(new DateTimeImmutable($c['last_booking_at'])), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <span class="vb-text-tertiary">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="vb-text-secondary">
                                <?= htmlspecialchars(\App\Engine\Locale::dateLong(new DateTimeImmutable($c['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="vb-pagination">
        <?php if ($page > 1): ?>
            <a href="<?= $baseUrl ?>/customers?page=<?= $page - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
               class="vb-btn vb-btn-ghost vb-btn-sm">
                <i data-lucide="chevron-left" style="width: 14px; height: 14px;"></i>
                <?= __('admin.common.previous') ?>
            </a>
        <?php endif; ?>
        <span class="vb-pagination-info">
            <?= str_replace([':page', ':total'], [(string) $page, (string) $totalPages], __('admin.common.page_of')) ?>
        </span>
        <?php if ($page < $totalPages): ?>
            <a href="<?= $baseUrl ?>/customers?page=<?= $page + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
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
