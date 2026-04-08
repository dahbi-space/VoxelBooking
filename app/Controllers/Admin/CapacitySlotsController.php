<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\FormState;
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
            'flash'         => FormState::getToast(),
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

        $redirectUrl = "/admin/tenants/{$tenantId}/capacity-slots";

        // ── Parse raw input ──
        $dayOfWeek    = (int) $request->string('day_of_week');
        $startTime    = trim($request->string('start_time'));
        $endTime      = trim($request->string('end_time'));
        $rawCapacity  = $request->string('max_capacity');
        $rawMinParty  = $request->string('min_party_size') ?: '1';
        $rawMaxParty  = $request->string('max_party_size');
        $label        = trim($request->string('label')) ?: null;

        // ── Validate ──
        $errors = [];

        // Day
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            $errors['day_of_week'] = __('admin.capacity_slots.error_invalid_day');
        }

        // Time format
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            $errors['start_time'] = __('admin.capacity_slots.error_invalid_time');
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            $errors['end_time'] = __('admin.capacity_slots.error_invalid_time');
        }
        if (empty($errors['start_time']) && empty($errors['end_time']) && $startTime >= $endTime) {
            $errors['end_time'] = __('admin.capacity_slots.error_end_before_start');
        }

        // Numeric bounds — INT UNSIGNED max is 4294967295, but 10000 seats
        // is already beyond any real venue. Reject absurd values early.
        $maxCapacity  = (int) $rawCapacity;
        $minPartySize = (int) $rawMinParty;
        $maxPartySize = (int) $rawMaxParty;

        if ($maxCapacity < 1 || $maxCapacity > 10000) {
            $errors['max_capacity'] = __('admin.capacity_slots.error_capacity_range');
        }
        if ($minPartySize < 1 || $minPartySize > 1000) {
            $errors['min_party_size'] = __('admin.capacity_slots.error_party_size_range');
        }
        if ($maxPartySize < 1 || $maxPartySize > 1000) {
            $errors['max_party_size'] = __('admin.capacity_slots.error_party_size_range');
        }

        // Party size consistency
        if (empty($errors['min_party_size']) && empty($errors['max_party_size'])) {
            if ($minPartySize > $maxPartySize) {
                $errors['min_party_size'] = __('admin.capacity_slots.error_min_exceeds_max');
            }
        }
        if (empty($errors['max_party_size']) && empty($errors['max_capacity'])) {
            if ($maxPartySize > $maxCapacity) {
                $errors['max_party_size'] = __('admin.capacity_slots.error_party_exceeds_capacity');
            }
        }

        // Duplicate slot check (same tenant + day + start + end)
        if (empty($errors)) {
            $existing = Database::query(
                'SELECT `id` FROM `capacity_slots`
                 WHERE `tenant_id` = ? AND `day_of_week` = ?
                   AND `start_time` = ? AND `end_time` = ?
                 LIMIT 1',
                [$tenantId, $dayOfWeek, $startTime . ':00', $endTime . ':00']
            );
            if (!empty($existing)) {
                $errors['start_time'] = __('admin.capacity_slots.error_duplicate_slot');
            }
        }

        // ── Bail on errors ──
        if (!empty($errors)) {
            FormState::flash([
                'day_of_week'    => (string) $dayOfWeek,
                'start_time'     => $startTime,
                'end_time'       => $endTime,
                'max_capacity'   => $rawCapacity,
                'min_party_size' => $rawMinParty,
                'max_party_size' => $rawMaxParty,
                'label'          => $label ?? '',
            ], $errors);

            $firstError = reset($errors);
            FormState::toast('error', $firstError);
            return Response::redirect($redirectUrl);
        }

        // ── Insert ──
        $id = Ulid::generate();
        Database::execute(
            'INSERT INTO `capacity_slots` (`id`, `tenant_id`, `day_of_week`, `start_time`, `end_time`, `max_capacity`, `min_party_size`, `max_party_size`, `label`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $tenantId, $dayOfWeek, $startTime . ':00', $endTime . ':00', $maxCapacity, $minPartySize, $maxPartySize, $label]
        );

        AuditLog::log('capacity_slot.created', 'capacity_slot', $id, [
            'day_of_week' => $dayOfWeek,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'max_capacity' => $maxCapacity,
            'label'       => $label,
        ], $tenantId);

        FormState::toast('success', __('admin.capacity_slots.flash_created'));
        return Response::redirect($redirectUrl);
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

        FormState::toast('success', $newState ? __('admin.capacity_slots.flash_activated') : __('admin.capacity_slots.flash_deactivated'));
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

        FormState::toast('success', __('admin.capacity_slots.flash_deleted'));
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


}
