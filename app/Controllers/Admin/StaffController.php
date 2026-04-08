<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\FormState;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\ImageUpload;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Ulid;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Staff CRUD controller (tenant-scoped).
 *
 * Access: operators + business owners (Auth::canManageTenant()).
 * Managers cannot access any of these routes.
 *
 * GET  /admin/tenants/{tenant_id}/staff                → list
 * GET  /admin/tenants/{tenant_id}/staff/create         → create form
 * POST /admin/tenants/{tenant_id}/staff/create         → store
 * GET  /admin/tenants/{tenant_id}/staff/{id}/edit      → edit form
 * POST /admin/tenants/{tenant_id}/staff/{id}/edit      → update
 * POST /admin/tenants/{tenant_id}/staff/{id}/activate  → activate
 * POST /admin/tenants/{tenant_id}/staff/{id}/deactivate→ deactivate
 * POST /admin/tenants/{tenant_id}/staff/{id}/reorder    → reorder (chevron swap)
 */
final class StaffController
{
    // ── List ──

    public function index(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (!$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        // Staff with service count via subquery
        $staff = Database::query(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM `service_staff` ss WHERE ss.`staff_id` = s.`id`) AS service_count
             FROM `staff` s
             WHERE s.`tenant_id` = ?
             ORDER BY s.`is_active` DESC, s.`sort_order` ASC, s.`name` ASC',
            [$tenantId]
        );

        return $this->render('admin.tenants.staff.index', __('admin.staff.title'), [
            'documentTitle' => __('admin.staff.title') . ' — ' . $tenant['name'],
            'tenant' => $tenant,
            'staff'  => $staff,
            'flash'  => FormState::getToast(),
        ], $tenantId);
    }

    // ── Create form ──

    public function create(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (!$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $services = $this->loadServices($tenantId);

        return $this->render('admin.tenants.staff.create', __('admin.staff.new'), [
            'documentTitle' => __('admin.staff.new') . ' — ' . $tenant['name'],
            'tenant'   => $tenant,
            'services' => $services,
            'flash'    => FormState::getToast(),
        ], $tenantId);
    }

    // ── Store ──

    public function store(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (!$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $name      = trim($request->string('name'));
        $email     = trim($request->string('email'));
        $phone     = trim($request->string('phone')) ?: null;
        $title     = trim($request->string('title')) ?: null;
        $bio       = trim($request->string('bio')) ?: null;
        $serviceIds = (array) ($_POST['service_ids'] ?? []);

        // Validate
        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.staff.error_name_required');
        }
        if ($email === '') {
            $errors[] = __('admin.staff.error_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('admin.staff.error_email_invalid');
        }

        // Tenant-scoped email uniqueness
        if ($email !== '' && empty($errors)) {
            $existing = Database::query(
                'SELECT `id` FROM `staff` WHERE `tenant_id` = ? AND `email` = ? LIMIT 1',
                [$tenantId, $email]
            );
            if (!empty($existing)) {
                $errors[] = __('admin.staff.error_email_taken');
            }
        }

        if (!empty($errors)) {
            FormState::toast('error', implode(' ', $errors));
            FormState::flashInput([
                'name' => $name, 'email' => $email, 'phone' => $phone,
                'title' => $title, 'bio' => $bio,
                'service_ids' => $serviceIds,
            ]);
            return Response::redirect("/admin/tenants/{$tenantId}/staff/create");
        }

        $staffId = Ulid::generate();

        // Handle avatar upload
        $avatarPath = null;
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $upload = ImageUpload::store('avatar', $_FILES['avatar'], $tenant['slug']);
            if ($upload['error']) {
                FormState::toast('error', $upload['error']);
                FormState::flashInput([
                    'name' => $name, 'email' => $email, 'phone' => $phone,
                    'title' => $title, 'bio' => $bio,
                    'service_ids' => $serviceIds,
                ]);
                return Response::redirect("/admin/tenants/{$tenantId}/staff/create");
            }
            $avatarPath = $upload['path'];
        }

        try {
            Database::transaction(function () use ($staffId, $tenantId, $name, $email, $phone, $title, $bio, $serviceIds, $avatarPath) {
                // Auto-assign sort_order = MAX + 1
                $maxRows = Database::query('SELECT COALESCE(MAX(`sort_order`), -1) AS m FROM `staff` WHERE `tenant_id` = ?', [$tenantId]);
                $nextOrder = ((int) $maxRows[0]['m']) + 1;

                Database::execute(
                    "INSERT INTO `staff` (`id`, `tenant_id`, `name`, `email`, `phone`, `title`, `bio`, `sort_order`, `avatar_path`, `is_active`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [$staffId, $tenantId, $name, $email, $phone, $title, $bio, $nextOrder, $avatarPath]
                );

                if (!empty($serviceIds)) {
                    $this->syncServicePivot($staffId, $serviceIds, $tenantId);
                }
            });
        } catch (\Throwable $e) {
            // Clean up uploaded file on failure
            if ($avatarPath) {
                ImageUpload::delete($avatarPath);
            }
            FormState::toast('error', __('admin.common.error_generic'));
            return Response::redirect("/admin/tenants/{$tenantId}/staff/create");
        }

        AuditLog::log('staff.created', 'staff', $staffId, [
            'tenant_id' => $tenantId,
            'name'      => $name,
            'email'     => AuditLog::hashEmail($email),
        ]);

        FormState::clear();
        FormState::toast('success', __('admin.staff.created'));
        return Response::redirect("/admin/tenants/{$tenantId}/staff");
    }

    // ── Edit form ──

    public function edit(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $staffId  = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (!$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $member = $this->loadStaff($staffId, $tenantId);
        if ($member === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/staff");
        }

        $services = $this->loadServices($tenantId);
        $linkedServiceIds = array_column(Database::query(
            'SELECT `service_id` FROM `service_staff` WHERE `staff_id` = ?',
            [$staffId]
        ), 'service_id');

        return $this->render('admin.tenants.staff.edit', __('admin.staff.edit'), [
            'documentTitle'    => $member['name'] . ' — ' . __('admin.staff.edit'),
            'tenant'           => $tenant,
            'member'           => $member,
            'services'         => $services,
            'linkedServiceIds' => $linkedServiceIds,
            'flash'            => FormState::getToast(),
        ], $tenantId);
    }

    // ── Update ──

    public function update(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $staffId  = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $member = $this->loadStaff($staffId, $tenantId);
        if ($member === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/staff");
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $name      = trim($request->string('name'));
        $email     = trim($request->string('email'));
        $phone     = trim($request->string('phone')) ?: null;
        $title     = trim($request->string('title')) ?: null;
        $bio       = trim($request->string('bio')) ?: null;
        $serviceIds = (array) ($_POST['service_ids'] ?? []);

        // Validate
        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.staff.error_name_required');
        }
        if ($email === '') {
            $errors[] = __('admin.staff.error_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('admin.staff.error_email_invalid');
        }

        // Tenant-scoped email uniqueness (exclude self)
        if ($email !== '' && empty($errors)) {
            $existing = Database::query(
                'SELECT `id` FROM `staff` WHERE `tenant_id` = ? AND `email` = ? AND `id` != ? LIMIT 1',
                [$tenantId, $email, $staffId]
            );
            if (!empty($existing)) {
                $errors[] = __('admin.staff.error_email_taken');
            }
        }

        if (!empty($errors)) {
            FormState::toast('error', implode(' ', $errors));
            FormState::flashInput([
                'name' => $name, 'email' => $email, 'phone' => $phone,
                'title' => $title, 'bio' => $bio,
                'service_ids' => $serviceIds,
            ]);
            return Response::redirect("/admin/tenants/{$tenantId}/staff/{$staffId}/edit");
        }

        // Track changes for audit log
        $changes = [];
        if ($member['name'] !== $name) $changes['name'] = $name;
        if ($member['email'] !== $email) $changes['email'] = AuditLog::hashEmail($email);
        if (($member['title'] ?? '') !== ($title ?? '')) $changes['title'] = $title;

        // Handle avatar upload / removal
        $avatarPath = $member['avatar_path'] ?? null; // preserve existing by default
        $removeAvatar = ($request->string('remove_avatar') === '1');

        if (!empty($_FILES['avatar']['tmp_name'])) {
            $upload = ImageUpload::store('avatar', $_FILES['avatar'], $tenant['slug'], $avatarPath);
            if ($upload['error']) {
                FormState::toast('error', $upload['error']);
                FormState::flashInput([
                    'name' => $name, 'email' => $email, 'phone' => $phone,
                    'title' => $title, 'bio' => $bio,
                    'service_ids' => $serviceIds,
                ]);
                return Response::redirect("/admin/tenants/{$tenantId}/staff/{$staffId}/edit");
            }
            $avatarPath = $upload['path'];
            $changes['avatar'] = 'uploaded';
        } elseif ($removeAvatar && $avatarPath) {
            ImageUpload::delete($avatarPath);
            $avatarPath = null;
            $changes['avatar'] = 'removed';
        }

        try {
            Database::transaction(function () use ($staffId, $tenantId, $name, $email, $phone, $title, $bio, $serviceIds, $avatarPath) {
                Database::execute(
                    'UPDATE `staff`
                     SET `name` = ?, `email` = ?, `phone` = ?, `title` = ?, `bio` = ?, `avatar_path` = ?
                     WHERE `id` = ? AND `tenant_id` = ?',
                    [$name, $email, $phone, $title, $bio, $avatarPath, $staffId, $tenantId]
                );

                // Sync service pivot (delete + re-insert)
                Database::execute('DELETE FROM `service_staff` WHERE `staff_id` = ?', [$staffId]);
                if (!empty($serviceIds)) {
                    $this->syncServicePivot($staffId, $serviceIds, $tenantId);
                }
            });
        } catch (\Throwable $e) {
            FormState::toast('error', __('admin.common.error_generic'));
            return Response::redirect("/admin/tenants/{$tenantId}/staff/{$staffId}/edit");
        }

        AuditLog::log('staff.updated', 'staff', $staffId, array_merge(
            ['tenant_id' => $tenantId],
            !empty($changes) ? ['changed' => $changes] : []
        ));

        FormState::clear();
        FormState::toast('success', __('admin.staff.updated'));
        return Response::redirect("/admin/tenants/{$tenantId}/staff");
    }

    // ── Activate ──

    public function activate(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $staffId  = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $member = $this->loadStaff($staffId, $tenantId);
        if ($member === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/staff");
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        Database::execute(
            'UPDATE `staff` SET `is_active` = 1 WHERE `id` = ? AND `tenant_id` = ?',
            [$staffId, $tenantId]
        );

        AuditLog::log('staff.activated', 'staff', $staffId, [
            'tenant_id' => $tenantId,
            'email'     => AuditLog::hashEmail($member['email']),
        ]);

        FormState::toast('success', __('admin.staff.activated'));
        return Response::redirect("/admin/tenants/{$tenantId}/staff");
    }

    // ── Deactivate ──

    public function deactivate(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $staffId  = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $member = $this->loadStaff($staffId, $tenantId);
        if ($member === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/staff");
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        Database::execute(
            'UPDATE `staff` SET `is_active` = 0 WHERE `id` = ? AND `tenant_id` = ?',
            [$staffId, $tenantId]
        );

        AuditLog::log('staff.deactivated', 'staff', $staffId, [
            'tenant_id' => $tenantId,
            'email'     => AuditLog::hashEmail($member['email']),
        ]);

        FormState::toast('success', __('admin.staff.deactivated'));
        return Response::redirect("/admin/tenants/{$tenantId}/staff");
    }

    // ── Reorder (move up / move down) ──

    public function reorder(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $staffId  = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $direction = $request->string('direction');
        if (!in_array($direction, ['up', 'down'], true)) {
            return Response::redirect("/admin/tenants/{$tenantId}/staff");
        }

        $member = $this->loadStaff($staffId, $tenantId);
        if ($member === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/staff");
        }

        $currentActive = (int) $member['is_active'];

        Database::transaction(function () use ($staffId, $tenantId, $currentActive, $direction) {
            // Step 1: Normalize sort_order to sequential 0,1,2,… within the active group.
            $siblings = Database::query(
                'SELECT `id` FROM `staff`
                 WHERE `tenant_id` = ? AND `is_active` = ?
                 ORDER BY `sort_order` ASC, `name` ASC',
                [$tenantId, $currentActive]
            );
            foreach ($siblings as $i => $row) {
                Database::execute(
                    'UPDATE `staff` SET `sort_order` = ? WHERE `id` = ? AND `tenant_id` = ?',
                    [$i, $row['id'], $tenantId]
                );
            }

            // Step 2: Re-read normalized sort_order
            $fresh = Database::query(
                'SELECT `sort_order` FROM `staff` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
                [$staffId, $tenantId]
            );
            $currentOrder = (int) $fresh[0]['sort_order'];

            // Step 3: Find adjacent sibling and swap
            if ($direction === 'up') {
                $rows = Database::query(
                    'SELECT `id`, `sort_order` FROM `staff`
                     WHERE `tenant_id` = ? AND `is_active` = ? AND `sort_order` < ?
                     ORDER BY `sort_order` DESC LIMIT 1',
                    [$tenantId, $currentActive, $currentOrder]
                );
            } else {
                $rows = Database::query(
                    'SELECT `id`, `sort_order` FROM `staff`
                     WHERE `tenant_id` = ? AND `is_active` = ? AND `sort_order` > ?
                     ORDER BY `sort_order` ASC LIMIT 1',
                    [$tenantId, $currentActive, $currentOrder]
                );
            }

            if (!empty($rows)) {
                $sibling = $rows[0];
                Database::execute(
                    'UPDATE `staff` SET `sort_order` = ? WHERE `id` = ? AND `tenant_id` = ?',
                    [(int) $sibling['sort_order'], $staffId, $tenantId]
                );
                Database::execute(
                    'UPDATE `staff` SET `sort_order` = ? WHERE `id` = ? AND `tenant_id` = ?',
                    [$currentOrder, $sibling['id'], $tenantId]
                );
            }
        });

        return Response::redirect("/admin/tenants/{$tenantId}/staff");
    }

    // ── Helpers ──

    private function canAccess(string $tenantId): bool
    {
        if (!Auth::canAccessTenant($tenantId)) {
            return false;
        }

        return Auth::canManageTenant();
    }

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT `id`, `name`, `slug`, `email`, `booking_pattern` FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );

        return $rows[0] ?? null;
    }

    private function isTimeslot(?array $tenant): bool
    {
        return ($tenant['booking_pattern'] ?? '') === 'timeslot';
    }

    private function loadStaff(string $staffId, string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `staff` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
            [$staffId, $tenantId]
        );

        return $rows[0] ?? null;
    }

    private function loadServices(string $tenantId): array
    {
        return Database::query(
            'SELECT `id`, `name` FROM `services` WHERE `tenant_id` = ? AND `is_active` = 1 ORDER BY `sort_order` ASC, `name` ASC',
            [$tenantId]
        );
    }

    /**
     * Insert service_staff pivot rows.
     * Only inserts rows for services that actually belong to this tenant.
     */
    private function syncServicePivot(string $staffId, array $serviceIds, string $tenantId): void
    {
        // Verify service IDs belong to this tenant
        if (empty($serviceIds)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $validServices = Database::query(
            "SELECT `id` FROM `services` WHERE `id` IN ({$placeholders}) AND `tenant_id` = ?",
            [...$serviceIds, $tenantId]
        );
        $validIds = array_column($validServices, 'id');

        foreach ($validIds as $serviceId) {
            Database::execute(
                'INSERT INTO `service_staff` (`service_id`, `staff_id`) VALUES (?, ?)',
                [$serviceId, $staffId]
            );
        }
    }

    private function render(string $template, string $pageTitle, array $extra, string $tenantId): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'staff',
            'csrfToken'  => CsrfMiddleware::generateToken(),
            'tenantId'   => $tenantId,
        ], $extra));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Owner or operator access required.',
            ], 403);
        }

        return View::response('admin.errors.403', [
            'user'      => Auth::user(),
            'version'   => Version::get(),
            'pageTitle' => '403',
            'csrfToken' => CsrfMiddleware::generateToken(),
        ], 403);
    }


}
