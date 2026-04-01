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
 * Service management controller (tenant-scoped, timeslot-only).
 *
 * Access: operators + business owners (Auth::canManageTenant()).
 */
final class ServiceController
{
    // ── Index ──

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

        $services = Database::query(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM `service_staff` ss WHERE ss.`service_id` = s.`id`) AS staff_count
             FROM `services` s
             WHERE s.`tenant_id` = ?
             ORDER BY s.`is_active` DESC, s.`sort_order` ASC, s.`name` ASC',
            [$tenantId]
        );

        return $this->render('admin.tenants.services.index', __('admin.services.title'), [
            'documentTitle' => __('admin.services.title') . ' — ' . $tenant['name'],
            'tenant'   => $tenant,
            'services' => $services,
            'flash'    => $this->flash(),
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

        $staff = $this->loadActiveStaff($tenantId);

        return $this->render('admin.tenants.services.create', __('admin.services.new'), [
            'documentTitle' => __('admin.services.new') . ' — ' . $tenant['name'],
            'tenant' => $tenant,
            'staff'  => $staff,
            'old'    => $_SESSION['_old_input'] ?? [],
            'flash'  => $this->flash(),
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

        $name        = trim($request->string('name'));
        $description = trim($request->string('description')) ?: null;
        $duration    = max(0, (int) $request->string('duration_minutes'));
        $price       = trim($request->string('price'));
        $priceLabel  = trim($request->string('price_label')) ?: null;
        $category    = trim($request->string('category')) ?: null;
        $color       = trim($request->string('color')) ?: null;
        $sortOrder   = max(0, (int) $request->string('sort_order'));
        $staffIds    = (array) ($_POST['staff_ids'] ?? []);

        // Validate
        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.services.error_name_required');
        }
        if ($duration <= 0) {
            $errors[] = __('admin.services.error_duration_required');
        }
        if ($color !== null && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = null; // Silently discard invalid color
        }

        $priceValue = null;
        if ($price !== '') {
            $priceValue = filter_var($price, FILTER_VALIDATE_FLOAT);
            if ($priceValue === false || $priceValue < 0) {
                $errors[] = __('admin.services.error_price_invalid');
                $priceValue = null;
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            $_SESSION['_old_input'] = $_POST;
            return Response::redirect("/admin/tenants/{$tenantId}/services/create");
        }

        // Validate staff_ids belong to tenant — reject if any are invalid
        if (!empty($staffIds)) {
            $validCount = $this->countValidStaff($staffIds, $tenantId);
            if ($validCount !== count($staffIds)) {
                $this->setFlash('error', __('admin.services.error_invalid_staff'));
                $_SESSION['_old_input'] = $_POST;
                return Response::redirect("/admin/tenants/{$tenantId}/services/create");
            }
        }

        $id = Ulid::generate();

        Database::transaction(function () use ($id, $tenantId, $name, $description, $duration, $priceValue, $priceLabel, $category, $color, $sortOrder, $staffIds) {
            Database::execute(
                'INSERT INTO `services`
                 (`id`, `tenant_id`, `name`, `description`, `duration_minutes`, `price`, `price_label`, `category`, `color`, `sort_order`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $tenantId, $name, $description, $duration, $priceValue, $priceLabel, $category, $color, $sortOrder]
            );

            foreach ($staffIds as $staffId) {
                Database::execute(
                    'INSERT INTO `service_staff` (`service_id`, `staff_id`) VALUES (?, ?)',
                    [$id, $staffId]
                );
            }
        });

        AuditLog::log('service.created', 'service', $id, [
            'tenant_id' => $tenantId,
            'name'      => $name,
        ]);

        unset($_SESSION['_old_input']);
        $this->setFlash('success', __('admin.services.created'));
        return Response::redirect("/admin/tenants/{$tenantId}/services");
    }

    // ── Edit form ──

    public function edit(Request $request): Response
    {
        $tenantId  = $request->getAttribute('tenant_id');
        $serviceId = $request->getAttribute('id');

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

        $service = $this->loadService($serviceId, $tenantId);
        if ($service === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/services");
        }

        $staff = $this->loadActiveStaff($tenantId);
        $linkedStaffIds = array_column(
            Database::query(
                'SELECT `staff_id` FROM `service_staff` WHERE `service_id` = ?',
                [$serviceId]
            ),
            'staff_id'
        );

        return $this->render('admin.tenants.services.edit', __('admin.services.edit'), [
            'documentTitle'  => $service['name'] . ' — ' . __('admin.services.edit'),
            'tenant'         => $tenant,
            'service'        => $service,
            'staff'          => $staff,
            'linkedStaffIds' => $linkedStaffIds,
            'old'            => $_SESSION['_old_input'] ?? [],
            'flash'          => $this->flash(),
        ], $tenantId);
    }

    // ── Update ──

    public function update(Request $request): Response
    {
        $tenantId  = $request->getAttribute('tenant_id');
        $serviceId = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $service = $this->loadService($serviceId, $tenantId);
        if ($service === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/services");
        }

        $name        = trim($request->string('name'));
        $description = trim($request->string('description')) ?: null;
        $duration    = max(0, (int) $request->string('duration_minutes'));
        $price       = trim($request->string('price'));
        $priceLabel  = trim($request->string('price_label')) ?: null;
        $category    = trim($request->string('category')) ?: null;
        $color       = trim($request->string('color')) ?: null;
        $sortOrder   = max(0, (int) $request->string('sort_order'));
        $staffIds    = (array) ($_POST['staff_ids'] ?? []);

        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.services.error_name_required');
        }
        if ($duration <= 0) {
            $errors[] = __('admin.services.error_duration_required');
        }
        if ($color !== null && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = null;
        }

        $priceValue = null;
        if ($price !== '') {
            $priceValue = filter_var($price, FILTER_VALIDATE_FLOAT);
            if ($priceValue === false || $priceValue < 0) {
                $errors[] = __('admin.services.error_price_invalid');
                $priceValue = null;
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            $_SESSION['_old_input'] = $_POST;
            return Response::redirect("/admin/tenants/{$tenantId}/services/{$serviceId}/edit");
        }

        // Validate staff_ids belong to tenant — reject if any are invalid
        if (!empty($staffIds)) {
            $validCount = $this->countValidStaff($staffIds, $tenantId);
            if ($validCount !== count($staffIds)) {
                $this->setFlash('error', __('admin.services.error_invalid_staff'));
                $_SESSION['_old_input'] = $_POST;
                return Response::redirect("/admin/tenants/{$tenantId}/services/{$serviceId}/edit");
            }
        }

        Database::transaction(function () use ($serviceId, $tenantId, $name, $description, $duration, $priceValue, $priceLabel, $category, $color, $sortOrder, $staffIds) {
            Database::execute(
                'UPDATE `services` SET
                    `name` = ?, `description` = ?, `duration_minutes` = ?,
                    `price` = ?, `price_label` = ?, `category` = ?,
                    `color` = ?, `sort_order` = ?
                 WHERE `id` = ? AND `tenant_id` = ?',
                [$name, $description, $duration, $priceValue, $priceLabel, $category, $color, $sortOrder, $serviceId, $tenantId]
            );

            // Re-sync staff pivot atomically
            Database::execute('DELETE FROM `service_staff` WHERE `service_id` = ?', [$serviceId]);
            foreach ($staffIds as $staffId) {
                Database::execute(
                    'INSERT INTO `service_staff` (`service_id`, `staff_id`) VALUES (?, ?)',
                    [$serviceId, $staffId]
                );
            }
        });

        AuditLog::log('service.updated', 'service', $serviceId, [
            'tenant_id' => $tenantId,
            'name'      => $name,
        ]);

        unset($_SESSION['_old_input']);
        $this->setFlash('success', __('admin.services.updated'));
        return Response::redirect("/admin/tenants/{$tenantId}/services");
    }

    // ── Activate ──

    public function activate(Request $request): Response
    {
        $tenantId  = $request->getAttribute('tenant_id');
        $serviceId = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $service = $this->loadService($serviceId, $tenantId);
        if ($service === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/services");
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        Database::execute(
            'UPDATE `services` SET `is_active` = 1 WHERE `id` = ? AND `tenant_id` = ?',
            [$serviceId, $tenantId]
        );

        AuditLog::log('service.activated', 'service', $serviceId, [
            'tenant_id' => $tenantId,
        ]);

        $this->setFlash('success', __('admin.services.activated'));
        return Response::redirect("/admin/tenants/{$tenantId}/services");
    }

    // ── Deactivate ──

    public function deactivate(Request $request): Response
    {
        $tenantId  = $request->getAttribute('tenant_id');
        $serviceId = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $service = $this->loadService($serviceId, $tenantId);
        if ($service === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/services");
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isTimeslot($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        Database::execute(
            'UPDATE `services` SET `is_active` = 0 WHERE `id` = ? AND `tenant_id` = ?',
            [$serviceId, $tenantId]
        );

        AuditLog::log('service.deactivated', 'service', $serviceId, [
            'tenant_id' => $tenantId,
        ]);

        $this->setFlash('success', __('admin.services.deactivated'));
        return Response::redirect("/admin/tenants/{$tenantId}/services");
    }

    // ── Helpers ──

    private function canAccess(string $tenantId): bool
    {
        return Auth::canAccessTenant($tenantId) && Auth::canManageTenant();
    }

    private function isTimeslot(?array $tenant): bool
    {
        return ($tenant['booking_pattern'] ?? '') === 'timeslot';
    }

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT `id`, `name`, `slug`, `email`, `booking_pattern`, `currency` FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );
        return $rows[0] ?? null;
    }

    private function loadService(string $serviceId, string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `services` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
            [$serviceId, $tenantId]
        );
        return $rows[0] ?? null;
    }

    private function loadActiveStaff(string $tenantId): array
    {
        return Database::query(
            'SELECT `id`, `name`, `title` FROM `staff`
             WHERE `tenant_id` = ? AND `is_active` = 1
             ORDER BY `sort_order` ASC, `name` ASC',
            [$tenantId]
        );
    }

    /**
     * Count how many of the given staff IDs belong to this tenant.
     */
    private function countValidStaff(array $staffIds, string $tenantId): int
    {
        if (empty($staffIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
        $rows = Database::query(
            "SELECT COUNT(*) as cnt FROM `staff` WHERE `id` IN ({$placeholders}) AND `tenant_id` = ?",
            [...$staffIds, $tenantId]
        );

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    private function render(string $template, string $pageTitle, array $extra, string $tenantId): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'services',
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
