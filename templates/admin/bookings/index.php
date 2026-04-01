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

<!-- Status filter pills -->
<?php
$pillFilters = array_diff_key($filters ?? [], ['status' => '', 'page' => '']);
$pillBase = array_filter($pillFilters, fn($v) => $v !== null && $v !== '');
?>
<div class="vb-filter-pills vb-fade-in-up stagger-1">
    <a href="<?= htmlspecialchars($baseUrl . ($pillBase ? '?' . http_build_query($pillBase) : ''), ENT_QUOTES, 'UTF-8') ?>"
       class="vb-filter-pill <?= empty($filters['status']) ? 'is-active' : '' ?>">
        <?= __('admin.bookings.filter_all') ?>
    </a>
    <?php foreach (['confirmed', 'pending', 'cancelled', 'completed', 'no_show', 'rescheduled'] as $s): ?>
        <a href="<?= htmlspecialchars($baseUrl . '?' . http_build_query(array_filter(array_merge($pillBase, ['status' => $s, 'page' => 1]), fn($v) => $v !== null && $v !== '')), ENT_QUOTES, 'UTF-8') ?>"
           class="vb-filter-pill <?= ($filters['status'] ?? '') === $s ? 'is-active' : '' ?>">
            <?= __('admin.bookings.status_' . $s) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Date/search filters -->
<div class="vb-card vb-fade-in-up stagger-2 vb-mb-md">
    <form method="GET" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" class="vb-filter-bar">
        <?php if (!empty($filters['status'])): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($filters['status'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (!empty($sort) && $sort !== 'start_datetime'): ?>
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <?php if (!empty($direction) && $direction !== 'desc'): ?>
            <input type="hidden" name="direction" value="<?= htmlspecialchars($direction, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="vb-form-group">
            <label for="filter_from" class="vb-label"><?= __('admin.bookings.filter_from') ?></label>
            <input type="date" id="filter_from" name="from" class="vb-input vb-input-sm"
                   value="<?= htmlspecialchars($filters['from'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="vb-form-group">
            <label for="filter_to" class="vb-label"><?= __('admin.bookings.filter_to') ?></label>
            <input type="date" id="filter_to" name="to" class="vb-input vb-input-sm"
                   value="<?= htmlspecialchars($filters['to'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php if ($showTenantColumn): ?>
        <div class="vb-form-group vb-form-group--grow">
            <label for="filter_search" class="vb-label"><?= __('admin.bookings.filter_search') ?></label>
            <input type="text" id="filter_search" name="search" class="vb-input vb-input-sm"
                   placeholder="<?= __('admin.bookings.filter_search') ?>"
                   value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php endif; ?>
        <button type="submit" class="vb-btn vb-btn-primary vb-btn-sm">
            <i data-lucide="filter"></i>
            <?= __('admin.bookings.filter_apply') ?>
        </button>
    </form>
</div>

<?php if (empty($bookings)): ?>
    <div class="vb-empty vb-animate-in">
        <i data-lucide="calendar" class="vb-empty-icon"></i>
        <div class="vb-empty-title"><?= __('admin.bookings.empty_title') ?></div>
        <div class="vb-empty-desc"><?= __('admin.bookings.empty_desc') ?></div>
    </div>
<?php else: ?>
    <div class="vb-card">
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
                        <td>
                            <div class="vb-cell-primary"><?= htmlspecialchars($b['customer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="vb-cell-secondary"><?= htmlspecialchars($b['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><?= htmlspecialchars($b['service_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div class="vb-cell-primary"><?= date('M j, Y', strtotime($b['start_datetime'])) ?></div>
                            <div class="vb-cell-secondary">
                                <?= date('H:i', strtotime($b['start_datetime'])) ?> – <?= date('H:i', strtotime($b['end_datetime'])) ?>
                            </div>
                        </td>
                        <td>
                            <span class="vb-status vb-status-<?= htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= __('admin.bookings.status_' . $b['status']) ?>
                            </span>
                        </td>
                        <?php if ($showTenantColumn): ?>
                        <td>
                            <span class="vb-text-muted"><?= htmlspecialchars($b['tenant_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <?php endif; ?>
                        <td>
                            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars($b['id'], ENT_QUOTES, 'UTF-8') ?>"
                               class="vb-btn vb-btn-ghost vb-btn-sm"
                               title="<?= __('admin.bookings.view') ?>">
                                <i data-lucide="eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
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
<?php endif; ?>

<?php
$content = ob_get_clean();
include dirname(__DIR__, 2) . '/admin/layout.php';
