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
    <?php if ($flash['type'] === 'owner_credentials'): ?>
        <?php $creds = json_decode($flash['message'], true); ?>
        <div class="vb-alert vb-alert-info vb-fade-in-up">
            <i data-lucide="key"></i>
            <div>
                <div style="margin-bottom: 0.5rem;"><?= htmlspecialchars($creds['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="vb-credentials-card">
                    <div class="vb-credentials-row">
                        <span class="vb-credentials-label"><?= __('admin.tenants.owner_credentials_email') ?></span>
                        <code class="vb-credentials-value"><?= htmlspecialchars($creds['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                    <div class="vb-credentials-row">
                        <span class="vb-credentials-label"><?= __('admin.tenants.owner_credentials_password') ?></span>
                        <code class="vb-credentials-value"><?= htmlspecialchars($creds['password'] ?? '', ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                    <div class="vb-credentials-row">
                        <span class="vb-credentials-label"><?= __('admin.tenants.owner_credentials_login') ?></span>
                        <code class="vb-credentials-value"><?= htmlspecialchars($creds['login'] ?? '', ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                    <p class="vb-credentials-hint"><?= __('admin.tenants.owner_credentials_hint') ?></p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php include __DIR__ . '/../partials/alert.php'; ?>
    <?php endif; ?>
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
                                <button type="button"
                                        class="vb-copy-btn vb-copy-btn-ghost"
                                        data-copy-url="<?= htmlspecialchars((isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/book/' . $tenant['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                        @click="copyBookingUrl"
                                        title="<?= __('admin.common.copy_booking_url') ?>">
                                    <span class="vb-copy-icon"><i data-lucide="copy"></i></span>
                                    <span class="vb-copy-check"><i data-lucide="check"></i></span>
                                </button>
                                <form method="POST" action="/admin/tenants/<?= htmlspecialchars($tenant['id'], ENT_QUOTES, 'UTF-8') ?>/impersonate" class="vb-inline-form">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="vb-btn vb-btn-ghost vb-btn-sm" title="<?= __('admin.impersonation.start') ?>">
                                        <i data-lucide="eye"></i>
                                    </button>
                                </form>
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
