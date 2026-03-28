<?php
/**
 * Bookings list — shared template for operator and tenant-context views.
 *
 * Variables: $user, $version, $csrfToken, $bookings, $total, $page, $perPage,
 *            $showTenantColumn, $filters, $flash, $pageTitle, $activePage, $backUrl
 *            Optional: $tenantId
 */
$activePage = 'bookings';
$totalPages = max(1, (int) ceil($total / $perPage));

$filterParams = http_build_query(array_filter($filters ?? [], fn($v) => $v !== null && $v !== ''));
$baseUrl = $backUrl ?? '/admin/bookings';

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
</div>

<!-- Filters -->
<div class="vb-card vb-fade-in-up stagger-1 vb-mb-md">
    <form method="GET" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>" class="vb-filter-bar">
        <div class="vb-form-group vb-form-group--min">
            <label for="filter_status" class="vb-label"><?= __('admin.bookings.status') ?></label>
            <select id="filter_status" name="status" class="vb-select vb-select-sm">
                <option value=""><?= __('admin.bookings.filter_all') ?></option>
                <?php foreach (['pending','confirmed','cancelled','completed','no_show','rescheduled'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
                        <?= __('admin.bookings.status_' . $s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
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
                        <th><?= __('admin.bookings.customer') ?></th>
                        <th><?= __('admin.bookings.service') ?></th>
                        <th><?= __('admin.bookings.date_time') ?></th>
                        <th><?= __('admin.bookings.status') ?></th>
                        <?php if ($showTenantColumn): ?>
                        <th><?= __('admin.bookings.tenant') ?></th>
                        <?php endif; ?>
                        <th><?= __('admin.bookings.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $i => $b): ?>
                    <tr class="vb-fade-in-up stagger-<?= min($i + 1, 6) ?>">
                        <td>
                            <div class="vb-cell-name"><?= htmlspecialchars($b['customer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="vb-cell-detail"><?= htmlspecialchars($b['customer_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><?= htmlspecialchars($b['service_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div class="vb-cell-name"><?= date('M j, Y', strtotime($b['start_datetime'])) ?></div>
                            <div class="vb-cell-detail">
                                <?= date('H:i', strtotime($b['start_datetime'])) ?> – <?= date('H:i', strtotime($b['end_datetime'])) ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            $statusClass = match ($b['status']) {
                                'confirmed'   => 'vb-badge-success',
                                'pending'     => 'vb-badge-warning',
                                'cancelled'   => 'vb-badge-error',
                                'completed'   => 'vb-badge-default',
                                'no_show'     => 'vb-badge-error',
                                'rescheduled' => 'vb-badge-warning',
                                default       => 'vb-badge-default',
                            };
                            ?>
                            <span class="vb-badge <?= $statusClass ?>"><?= __('admin.bookings.status_' . $b['status']) ?></span>
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
