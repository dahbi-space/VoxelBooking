<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
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
            'flash'  => $this->flash(),
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
            'old'      => $_SESSION['_old_input'] ?? [],
            'flash'    => $this->flash(),
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
        $sortOrder = max(0, (int) $request->string('sort_order'));
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
            $this->setFlash('error', implode(' ', $errors));
            $_SESSION['_old_input'] = [
                'name' => $name, 'email' => $email, 'phone' => $phone,
                'title' => $title, 'bio' => $bio, 'sort_order' => $sortOrder,
                'service_ids' => $serviceIds,
            ];
            return Response::redirect("/admin/tenants/{$tenantId}/staff/create");
        }

        $staffId = Ulid::generate();

        try {
            Database::transaction(function () use ($staffId, $tenantId, $name, $email, $phone, $title, $bio, $sortOrder, $serviceIds) {
                Database::execute(
                    "INSERT INTO `staff` (`id`, `tenant_id`, `name`, `email`, `phone`, `title`, `bio`, `sort_order`, `is_active`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [$staffId, $tenantId, $name, $email, $phone, $title, $bio, $sortOrder]
                );

                if (!empty($serviceIds)) {
                    $this->syncServicePivot($staffId, $serviceIds, $tenantId);
                }
            });
        } catch (\Throwable $e) {
            $this->setFlash('error', __('admin.common.error_generic'));
            return Response::redirect("/admin/tenants/{$tenantId}/staff/create");
        }

        AuditLog::log('staff.created', 'staff', $staffId, [
            'tenant_id' => $tenantId,
            'name'      => $name,
            'email'     => AuditLog::hashEmail($email),
        ]);

        unset($_SESSION['_old_input']);
        $this->setFlash('success', __('admin.staff.created'));
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
            'old'              => $_SESSION['_old_input'] ?? [],
            'flash'            => $this->flash(),
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
        $sortOrder = max(0, (int) $request->string('sort_order'));
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
            $this->setFlash('error', implode(' ', $errors));
            $_SESSION['_old_input'] = [
                'name' => $name, 'email' => $email, 'phone' => $phone,
                'title' => $title, 'bio' => $bio, 'sort_order' => $sortOrder,
                'service_ids' => $serviceIds,
            ];
            return Response::redirect("/admin/tenants/{$tenantId}/staff/{$staffId}/edit");
        }

        // Track changes for audit log
        $changes = [];
        if ($member['name'] !== $name) $changes['name'] = $name;
        if ($member['email'] !== $email) $changes['email'] = AuditLog::hashEmail($email);
        if (($member['title'] ?? '') !== ($title ?? '')) $changes['title'] = $title;

        try {
            Database::transaction(function () use ($staffId, $tenantId, $name, $email, $phone, $title, $bio, $sortOrder, $serviceIds) {
                Database::execute(
                    'UPDATE `staff`
                     SET `name` = ?, `email` = ?, `phone` = ?, `title` = ?, `bio` = ?, `sort_order` = ?
                     WHERE `id` = ? AND `tenant_id` = ?',
                    [$name, $email, $phone, $title, $bio, $sortOrder, $staffId, $tenantId]
                );

                // Sync service pivot (delete + re-insert)
                Database::execute('DELETE FROM `service_staff` WHERE `staff_id` = ?', [$staffId]);
                if (!empty($serviceIds)) {
                    $this->syncServicePivot($staffId, $serviceIds, $tenantId);
                }
            });
        } catch (\Throwable $e) {
            $this->setFlash('error', __('admin.common.error_generic'));
            return Response::redirect("/admin/tenants/{$tenantId}/staff/{$staffId}/edit");
        }

        AuditLog::log('staff.updated', 'staff', $staffId, array_merge(
            ['tenant_id' => $tenantId],
            !empty($changes) ? ['changed' => $changes] : []
        ));

        unset($_SESSION['_old_input']);
        $this->setFlash('success', __('admin.staff.updated'));
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

        $this->setFlash('success', __('admin.staff.activated'));
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

        $this->setFlash('success', __('admin.staff.deactivated'));
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

    private function setFlash(string $type, string $message): void
    {
        Auth::startSession();
        $_SESSION['settings_flash'] = ['type' => $type, 'message' => $message];
    }

    private function flash(): ?array
    {
        $flash = $_SESSION['settings_flash'] ?? null;
        unset($_SESSION['settings_flash']);
        return $flash;
    }
}
