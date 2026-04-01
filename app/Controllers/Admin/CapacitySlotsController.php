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
 * Capacity slot management controller (tenant-scoped, capacity-pattern only).
 *
 * Provides CRUD for weekly time windows with max_capacity and max_party_size.
 * Access: operators + business owners (Auth::canManageTenant()).
 */
final class CapacitySlotsController
{

    public function index(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (($tenant['booking_pattern'] ?? '') !== 'capacity') {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $slots = Database::query(
            'SELECT * FROM `capacity_slots`
             WHERE `tenant_id` = ?
             ORDER BY `day_of_week` ASC, `start_time` ASC',
            [$tenantId]
        );

        return $this->render('admin.tenants.capacity-slots.index', __('admin.capacity_slots.title'), [
            'documentTitle' => __('admin.capacity_slots.title'),
            'tenant'        => $tenant,
            'tenantId'      => $tenantId,
            'slots'         => $slots,
            'flash'         => $this->flash(),
        ], $tenantId);
    }

    public function store(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || ($tenant['booking_pattern'] ?? '') !== 'capacity') {
            return Response::redirect('/admin/tenants');
        }

        $dayOfWeek    = (int) $request->string('day_of_week');
        $startTime    = trim($request->string('start_time'));
        $endTime      = trim($request->string('end_time'));
        $maxCapacity  = max(1, (int) $request->string('max_capacity'));
        $maxPartySize = max(1, (int) $request->string('max_party_size'));
        $label        = trim($request->string('label')) ?: null;

        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            $this->setFlash('error', __('admin.capacity_slots.error_invalid_day'));
            return Response::redirect("/admin/tenants/{$tenantId}/capacity-slots");
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            $this->setFlash('error', __('admin.capacity_slots.error_invalid_time'));
            return Response::redirect("/admin/tenants/{$tenantId}/capacity-slots");
        }

        if ($startTime >= $endTime) {
            $this->setFlash('error', __('admin.capacity_slots.error_end_before_start'));
            return Response::redirect("/admin/tenants/{$tenantId}/capacity-slots");
        }

        $id = Ulid::generate();
        Database::execute(
            'INSERT INTO `capacity_slots` (`id`, `tenant_id`, `day_of_week`, `start_time`, `end_time`, `max_capacity`, `max_party_size`, `label`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $tenantId, $dayOfWeek, $startTime . ':00', $endTime . ':00', $maxCapacity, $maxPartySize, $label]
        );

        AuditLog::log('capacity_slot.created', 'capacity_slot', $id, [
            'day_of_week' => $dayOfWeek,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'max_capacity' => $maxCapacity,
            'label'       => $label,
        ], $tenantId);

        $this->setFlash('success', __('admin.capacity_slots.flash_created'));
        return Response::redirect("/admin/tenants/{$tenantId}/capacity-slots");
    }

    public function toggleActive(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $slotId   = $request->getAttribute('id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        $slot = Database::query(
            'SELECT `id`, `is_active` FROM `capacity_slots` WHERE `id` = ? AND `tenant_id` = ?',
            [$slotId, $tenantId]
        );

        if (empty($slot)) {
            return Response::redirect("/admin/tenants/{$tenantId}/capacity-slots");
        }

        $newState = (int) $slot[0]['is_active'] === 1 ? 0 : 1;
        Database::execute(
            'UPDATE `capacity_slots` SET `is_active` = ?, `updated_at` = NOW() WHERE `id` = ?',
            [$newState, $slotId]
        );

        AuditLog::log(
            $newState ? 'capacity_slot.activated' : 'capacity_slot.deactivated',
            'capacity_slot',
            $slotId,
            ['is_active' => $newState],
            $tenantId,
        );

        $this->setFlash('success', $newState ? __('admin.capacity_slots.flash_activated') : __('admin.capacity_slots.flash_deactivated'));
        return Response::redirect("/admin/tenants/{$tenantId}/capacity-slots");
    }

    public function delete(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $slotId   = $request->getAttribute('id');

        if (!Auth::canAccessTenant($tenantId) || !Auth::canManageTenant()) {
            return $this->forbidden($request);
        }

        Database::execute(
            'DELETE FROM `capacity_slots` WHERE `id` = ? AND `tenant_id` = ?',
            [$slotId, $tenantId]
        );

        AuditLog::log('capacity_slot.deleted', 'capacity_slot', $slotId, [], $tenantId);

        $this->setFlash('success', __('admin.capacity_slots.flash_deleted'));
        return Response::redirect("/admin/tenants/{$tenantId}/capacity-slots");
    }

    // ── Private helpers (same pattern as ResourceController) ──

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query('SELECT * FROM `tenants` WHERE `id` = ? LIMIT 1', [$tenantId]);
        return $rows[0] ?? null;
    }

    private function render(string $template, string $pageTitle, array $extra, string $tenantId): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'capacity-slots',
            'csrfToken'  => CsrfMiddleware::generateToken(),
            'tenantId'   => $tenantId,
        ], $extra));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => __('admin.common.forbidden'),
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
