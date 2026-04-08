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
 * Blocked dates management controller (tenant-scoped).
 *
 * Shared by timeslot and resource patterns.
 * Timeslot: blocks by tenant-level or staff-level scope.
 * Resource: blocks by tenant-level or resource-level scope.
 *
 * Access: operators + business owners (Auth::canManageTenant()).
 *
 * GET  /admin/tenants/{tenant_id}/blocked-dates          → list
 * POST /admin/tenants/{tenant_id}/blocked-dates          → create
 * POST /admin/tenants/{tenant_id}/blocked-dates/{id}/delete → delete
 */
final class BlockedDatesController
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

        if (!$this->supportsBlockedDates($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $isResourcePattern = ($tenant['booking_pattern'] ?? '') === 'resource';

        // Load all blocked dates, split into tenant-level and entity-level
        $blockedDates = Database::query(
            'SELECT bd.*, s.`name` AS staff_name, r.`name` AS resource_name
             FROM `blocked_dates` bd
             LEFT JOIN `staff` s ON s.`id` = bd.`staff_id`
             LEFT JOIN `resources` r ON r.`id` = bd.`resource_id`
             WHERE bd.`tenant_id` = ?
             ORDER BY bd.`start_date` DESC, bd.`end_date` DESC',
            [$tenantId]
        );

        // Separate upcoming from past
        $today = date('Y-m-d');
        $upcoming = [];
        $past = [];
        foreach ($blockedDates as $bd) {
            if ($bd['end_date'] >= $today) {
                $upcoming[] = $bd;
            } else {
                $past[] = $bd;
            }
        }

        // Load active staff for the "per staff" selector (timeslot pattern)
        $staff = [];
        if (!$isResourcePattern) {
            $staff = Database::query(
                'SELECT `id`, `name`, `title` FROM `staff`
                 WHERE `tenant_id` = ? AND `is_active` = 1
                 ORDER BY `sort_order` ASC, `name` ASC',
                [$tenantId]
            );
        }

        // Load active resources for the "per resource" selector (resource pattern)
        $resources = [];
        if ($isResourcePattern) {
            $resources = Database::query(
                'SELECT `id`, `name` FROM `resources`
                 WHERE `tenant_id` = ? AND `is_active` = 1
                 ORDER BY `sort_order` ASC, `name` ASC',
                [$tenantId]
            );
        }

        return $this->render('admin.tenants.blocked-dates.index', __('admin.blocked_dates.title'), [
            'tenant'    => $tenant,
            'upcoming'  => $upcoming,
            'past'      => $past,
            'staff'     => $staff,
            'resources' => $resources,
            'flash'     => FormState::getToast(),
        ], $tenantId);
    }

    // ── Create ──

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

        if (!$this->supportsBlockedDates($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $isResourcePattern = ($tenant['booking_pattern'] ?? '') === 'resource';

        $startDate  = trim($request->string('start_date'));
        $endDate    = trim($request->string('end_date'));
        $reason     = trim($request->string('reason')) ?: null;
        $staffId    = $isResourcePattern ? null : (trim($request->string('staff_id')) ?: null);
        $resourceId = $isResourcePattern ? (trim($request->string('resource_id')) ?: null) : null;

        $redirectUrl = "/admin/tenants/{$tenantId}/blocked-dates";
        $oldInput = [
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'reason'      => $reason ?? '',
            'staff_id'    => $staffId ?? '',
            'resource_id' => $resourceId ?? '',
        ];

        // Validate dates
        $errors = [];
        if (!$this->isValidDate($startDate)) {
            $errors['start_date'] = __('admin.blocked_dates.error_invalid_date');
        }
        if (!$this->isValidDate($endDate)) {
            $errors['end_date'] = __('admin.blocked_dates.error_invalid_date');
        }

        if (empty($errors) && $endDate < $startDate) {
            $errors['end_date'] = __('admin.blocked_dates.error_end_before_start');
        }

        if (empty($errors['start_date']) && $startDate < date('Y-m-d')) {
            $errors['start_date'] = __('admin.blocked_dates.error_past_date');
        }

        // Validate staff belongs to this tenant (timeslot pattern)
        if ($staffId !== null && empty($errors)) {
            $staffRow = Database::query(
                'SELECT `id` FROM `staff` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
                [$staffId, $tenantId]
            );
            if (empty($staffRow)) {
                $errors['start_date'] = __('admin.blocked_dates.error_invalid_staff');
            }
        }

        // Validate resource belongs to this tenant (resource pattern)
        if ($resourceId !== null && empty($errors)) {
            $resRow = Database::query(
                'SELECT `id` FROM `resources` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
                [$resourceId, $tenantId]
            );
            if (empty($resRow)) {
                $errors['start_date'] = __('admin.blocked_dates.error_invalid_resource');
            }
        }

        // Check for overlapping dates in same scope
        if (empty($errors)) {
            $scopeClauses = [];
            $overlapParams = [$tenantId];

            if ($staffId !== null) {
                $scopeClauses[] = '`staff_id` = ?';
                $overlapParams[] = $staffId;
            } else {
                $scopeClauses[] = '`staff_id` IS NULL';
            }

            if ($resourceId !== null) {
                $scopeClauses[] = '`resource_id` = ?';
                $overlapParams[] = $resourceId;
            } else {
                $scopeClauses[] = '`resource_id` IS NULL';
            }

            $scopeWhere = implode(' AND ', $scopeClauses);
            $overlapParams = array_merge($overlapParams, [$endDate, $startDate, $endDate, $startDate, $startDate, $endDate]);

            $existing = Database::query(
                "SELECT `id` FROM `blocked_dates`
                 WHERE `tenant_id` = ? AND {$scopeWhere}
                   AND (
                       (`start_date` <= ? AND `end_date` >= ?)
                       OR (`start_date` <= ? AND `end_date` >= ?)
                       OR (`start_date` >= ? AND `end_date` <= ?)
                   )
                 LIMIT 1",
                $overlapParams
            );

            if (!empty($existing)) {
                $errors['start_date'] = __('admin.blocked_dates.error_overlap');
            }
        }

        if (!empty($errors)) {
            FormState::flash($oldInput, $errors);
            $firstError = reset($errors);
            FormState::toast('error', $firstError);
            return Response::redirect($redirectUrl);
        }

        $id = Ulid::generate();
        Database::execute(
            'INSERT INTO `blocked_dates` (`id`, `tenant_id`, `staff_id`, `resource_id`, `start_date`, `end_date`, `reason`)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $tenantId, $staffId, $resourceId, $startDate, $endDate, $reason]
        );

        AuditLog::log('blocked_date.created', 'blocked_date', $id, [
            'tenant_id'   => $tenantId,
            'staff_id'    => $staffId,
            'resource_id' => $resourceId,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'reason'      => $reason,
        ]);

        FormState::toast('success', __('admin.blocked_dates.created'));
        return Response::redirect("/admin/tenants/{$tenantId}/blocked-dates");
    }

    // ── Delete ──

    public function delete(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $id       = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (!$this->supportsBlockedDates($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        // Verify the blocked date belongs to this tenant
        $bd = Database::query(
            'SELECT * FROM `blocked_dates` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
            [$id, $tenantId]
        );

        if (empty($bd)) {
            FormState::toast('error', __('admin.blocked_dates.not_found'));
            return Response::redirect("/admin/tenants/{$tenantId}/blocked-dates");
        }

        Database::execute(
            'DELETE FROM `blocked_dates` WHERE `id` = ? AND `tenant_id` = ?',
            [$id, $tenantId]
        );

        AuditLog::log('blocked_date.deleted', 'blocked_date', $id, [
            'tenant_id'  => $tenantId,
            'start_date' => $bd[0]['start_date'],
            'end_date'   => $bd[0]['end_date'],
            'reason'     => $bd[0]['reason'],
        ]);

        FormState::toast('success', __('admin.blocked_dates.deleted'));
        return Response::redirect("/admin/tenants/{$tenantId}/blocked-dates");
    }

    // ── Helpers ──

    private function canAccess(string $tenantId): bool
    {
        return Auth::canAccessTenant($tenantId) && Auth::canManageTenant();
    }

    private function supportsBlockedDates(array $tenant): bool
    {
        return in_array($tenant['booking_pattern'] ?? '', ['timeslot', 'resource'], true);
    }

    private function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && strtotime($date) !== false;
    }

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
            'activePage' => 'blocked-dates',
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
