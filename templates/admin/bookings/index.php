<?php
/**
 * Bookings list — shared template for operator and tenant-context views.
 *
 * Variables: $user, $version, $csrfToken, $bookings, $total, $page, $perPage,
 *            $showTenantColumn, $filters, $flash, $pageTitle, $activePage, $backUrl,
 *            $sort, $direction
 *            Optional: $tenantId
 */
$activePage = 'bookings';
$totalPages = max(1, (int) ceil($total / $perPage));

$filterParams = http_build_query(array_filter($filters ?? [], fn($v) => $v !== null && $v !== ''));
$baseUrl = $backUrl ?? '/admin/bookings';

// Sort header helper — generates a clickable <a> with sort indicator
if (!function_exists('bookingSortHeader')) {
    function bookingSortHeader(string $column, string $label, string $currentSort, string $currentDir, string $baseUrl, array $filters): string {
        $isActive = $currentSort === $column;
        $nextDir = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
        $class = 'vb-th-sort' . ($isActive ? ($currentDir === 'asc' ? ' is-asc' : ' is-desc') : '');
        $params = array_merge($filters, ['sort' => $column, 'direction' => $nextDir, 'page' => 1]);
        $qs = http_build_query(array_filter($params, fn($v) => $v !== null && $v !== ''));
        $href = htmlspecialchars($baseUrl . ($qs ? '?' . $qs : ''), ENT_QUOTES, 'UTF-8');
        return "<a href=\"{$href}\" class=\"{$class}\">{$label}</a>";
    }
}

ob_start();
?>

<?php if ($flash ?? null): ?>
    <?php include __DIR__ . '/../../partials/alert.php'; ?>
<?php endif; ?>

<div class="vb-page-header">
    <div>
        <h2 class="vb-page-title">
            <i data-lucide="calendar" class="vb-page-header-icon"></i>
            <?= __('admin.bookings.title') ?>
        </h2>
        <div class="vb-page-subtitle"><?= $total ?> total</div>
    </div>
    <?php if (isset($tenantId) && $tenantId): ?>
    <a href="/admin/tenants/<?= htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8') ?>/bookings/create"
       class="vb-btn vb-btn-primary vb-btn-sm" id="btn-new-booking">
        <i data-lucide="plus" style="width: 14px; height: 14px;"></i>
        <?= __('admin.bookings.new_booking') ?>
    </a>
    <?php endif; ?>
</div>

<?php if (empty($bookings)): ?>
    <div class="vb-empty vb-animate-in">
        <i data-lucide="calendar" class="vb-empty-icon"></i>
        <div class="vb-empty-title"><?= __('admin.bookings.empty_title') ?></div>
        <div class="vb-empty-desc"><?= __('admin.bookings.empty_desc') ?></div>
    </div>
<?php else: ?>
    <div class="vb-table-container">
        <div class="vb-table-toolbar">
            <div class="vb-filter-tabs">
                <?php
                $pillFilters = array_diff_key($filters ?? [], ['status' => '', 'page' => '']);
                $pillBase = array_filter($pillFilters, fn($v) => $v !== null && $v !== '');
                $statusTabs = ['', 'confirmed', 'pending', 'waitlisted', 'cancelled', 'completed', 'no_show', 'rescheduled'];
                $statusLabels = [
                    ''            => __('admin.bookings.filter_all'),
                    'confirmed'   => __('admin.bookings.status_confirmed'),
                    'pending'     => __('admin.bookings.status_pending'),
                    'waitlisted'  => __('admin.bookings.status_waitlisted'),
                    'cancelled'   => __('admin.bookings.status_cancelled'),
                    'completed'   => __('admin.bookings.status_completed'),
                    'no_show'     => __('admin.bookings.status_no_show'),
                    'rescheduled' => __('admin.bookings.status_rescheduled'),
                ];
                foreach ($statusTabs as $s):
                    $tabQs = $s ? http_build_query(array_filter(array_merge($pillBase, ['status' => $s, 'page' => 1]), fn($v) => $v !== null && $v !== '')) : ($pillBase ? http_build_query($pillBase) : '');
                ?>
                <a href="<?= htmlspecialchars($baseUrl . ($tabQs ? '?' . $tabQs : ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="vb-filter-tab <?= ($filters['status'] ?? '') === $s ? 'active' : '' ?>">
                    <?= $statusLabels[$s] ?>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="vb-table-toolbar-right">
                <form method="GET" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" class="vb-table-toolbar-filters">
                    <?php if (!empty($filters['status'])): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filters['status'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <input type="date" name="from" class="vb-input vb-input-sm"
                           value="<?= htmlspecialchars($filters['from'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           title="<?= __('admin.bookings.filter_from') ?>">
                    <input type="date" name="to" class="vb-input vb-input-sm"
                           value="<?= htmlspecialchars($filters['to'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           title="<?= __('admin.bookings.filter_to') ?>">
                    <?php if ($showTenantColumn): ?>
                    <input type="text" name="search" class="vb-input vb-input-sm"
                           placeholder="<?= __('admin.bookings.filter_search') ?>"
                           value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <button type="submit" class="vb-btn vb-btn-primary vb-btn-sm">
                        <i data-lucide="filter"></i>
                    </button>
                </form>
            </div>
        </div>
        <div class="vb-table-wrap">
            <table class="vb-table">
                <thead>
                    <tr>
                        <th><?= bookingSortHeader('customer', __('admin.bookings.customer'), $sort, $direction, $baseUrl, $filters) ?></th>
                        <th><?= bookingSortHeader('service', __('admin.bookings.service'), $sort, $direction, $baseUrl, $filters) ?></th>
                        <th><?= bookingSortHeader('start_datetime', __('admin.bookings.date_time'), $sort, $direction, $baseUrl, $filters) ?></th>
                        <th><?= bookingSortHeader('status', __('admin.bookings.status'), $sort, $direction, $baseUrl, $filters) ?></th>
                        <?php if ($showTenantColumn): ?>
                        <th><?= bookingSortHeader('tenant', __('admin.bookings.tenant'), $sort, $direction, $baseUrl, $filters) ?></th>
                        <?php endif; ?>
                        <th><?= __('admin.bookings.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $i => $b): ?>
                    <tr class="vb-fade-in-up stagger-<?= min($i + 1, 6) ?>">
                        <td data-label="<?= __('admin.bookings.customer') ?>">
                            <div class="vb-cell-primary"><?= htmlspecialchars($b['customer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="vb-cell-secondary"><?= htmlspecialchars($b['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td data-label="<?= __('admin.bookings.service') ?>">
                            <?= htmlspecialchars(booking_display_label($b), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td data-label="<?= __('admin.bookings.date_time') ?>">
                            <div class="vb-cell-primary"><?= date('M j, Y', strtotime($b['start_datetime'])) ?></div>
                            <div class="vb-cell-secondary">
                                <?= date('H:i', strtotime($b['start_datetime'])) ?> – <?= date('H:i', strtotime($b['end_datetime'])) ?>
                            </div>
                        </td>
                        <td data-label="<?= __('admin.bookings.status') ?>">
                            <span class="vb-status vb-status-<?= htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= __('admin.bookings.status_' . $b['status']) ?>
                            </span>
                        </td>
                        <?php if ($showTenantColumn): ?>
                        <td data-label="<?= __('admin.bookings.tenant') ?>">
                            <span class="vb-text-muted"><?= htmlspecialchars($b['tenant_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <?php endif; ?>
                        <td data-label="<?= __('admin.bookings.actions') ?>">
                            <div class="vb-action-group">
                                <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($b['id'], ENT_QUOTES, 'UTF-8') ?>"
                                   class="vb-btn vb-btn-ghost vb-btn-sm"
                                   title="<?= __('admin.bookings.view') ?>">
                                    <i data-lucide="eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="vb-pagination">
            <span class="vb-text-muted">
                <?= str_replace([':page', ':total'], [(string) $page, (string) $totalPages], __('admin.bookings.page_info')) ?>
            </span>
            <div class="vb-pagination-nav">
                <?php if ($page > 1): ?>
                    <a href="<?= $baseUrl ?>?page=<?= $page - 1 ?><?= $filterParams ? '&' . $filterParams : '' ?>"
                       class="vb-btn vb-btn-ghost vb-btn-sm">
                        <i data-lucide="chevron-left"></i>
                        <?= __('admin.bookings.previous') ?>
                    </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $baseUrl ?>?page=<?= $page + 1 ?><?= $filterParams ? '&' . $filterParams : '' ?>"
                       class="vb-btn vb-btn-ghost vb-btn-sm">
                        <?= __('admin.bookings.next') ?>
                        <i data-lucide="chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
