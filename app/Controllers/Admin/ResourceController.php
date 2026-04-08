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
 * Resource management controller (tenant-scoped, resource-pattern only).
 *
 * Access: operators + business owners (Auth::canManageTenant()).
 */
final class ResourceController
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

        if (!$this->isResource($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $resources = Database::query(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM `bookings` b
                     WHERE b.`resource_id` = r.`id`
                     AND b.`status` IN (\'confirmed\', \'rescheduled\')
                    ) AS active_bookings
             FROM `resources` r
             WHERE r.`tenant_id` = ?
             ORDER BY r.`is_active` DESC, r.`sort_order` ASC, r.`name` ASC',
            [$tenantId]
        );

        // Decode amenities JSON
        foreach ($resources as &$r) {
            $r['amenities'] = $r['amenities'] ? json_decode($r['amenities'], true) : [];
        }
        unset($r);

        return $this->render('admin.tenants.resources.index', __('admin.resources.title'), [
            'documentTitle' => __('admin.resources.title') . ' — ' . $tenant['name'],
            'tenant'    => $tenant,
            'resources' => $resources,
            'flash'     => FormState::getToast(),
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

        if (!$this->isResource($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        return $this->render('admin.tenants.resources.create', __('admin.resources.new'), [
            'documentTitle' => __('admin.resources.new') . ' — ' . $tenant['name'],
            'tenant' => $tenant,
            'old' => FormState::old(),
            'flash'  => FormState::getToast(),
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

        if (!$this->isResource($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $name        = trim($request->string('name'));
        $description = trim($request->string('description')) ?: null;
        $capacity    = max(1, (int) $request->string('capacity'));
        $price       = trim($request->string('price_per_night'));
        $minStay     = max(1, (int) $request->string('min_stay_nights'));
        $maxStay     = max(1, (int) $request->string('max_stay_nights'));
        $amenitiesRaw = trim($request->string('amenities'));

        // Validate
        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.resources.error_name_required');
        }

        $priceValue = null;
        if ($price !== '') {
            $priceValue = filter_var($price, FILTER_VALIDATE_FLOAT);
            if ($priceValue === false || $priceValue < 0) {
                $errors[] = __('admin.resources.error_price_invalid');
                $priceValue = null;
            }
        }

        if ($maxStay < $minStay) {
            $errors[] = __('admin.resources.error_stay_range');
        }

        if (!empty($errors)) {
            FormState::toast('error', implode(' ', $errors));
            FormState::flashInput([
                'name'            => $request->string('name'),
                'description'     => $request->string('description'),
                'capacity'        => $request->string('capacity'),
                'price_per_night' => $request->string('price_per_night'),
                'min_stay_nights' => $request->string('min_stay_nights'),
                'max_stay_nights' => $request->string('max_stay_nights'),
                'amenities'       => $request->string('amenities'),
            ]);
            return Response::redirect("/admin/tenants/{$tenantId}/resources/create");
        }

        // Parse amenities as comma-separated tags → JSON array
        $amenities = $this->parseAmenities($amenitiesRaw);

        $id = Ulid::generate();

        // Handle cover image upload
        $coverPath = null;
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $upload = ImageUpload::store('cover', $_FILES['cover_image'], $tenant['slug']);
            if ($upload['error']) {
                FormState::toast('error', $upload['error']);
                FormState::flashInput([
                'name'            => $request->string('name'),
                'description'     => $request->string('description'),
                'capacity'        => $request->string('capacity'),
                'price_per_night' => $request->string('price_per_night'),
                'min_stay_nights' => $request->string('min_stay_nights'),
                'max_stay_nights' => $request->string('max_stay_nights'),
                'amenities'       => $request->string('amenities'),
            ]);
                return Response::redirect("/admin/tenants/{$tenantId}/resources/create");
            }
            $coverPath = $upload['path'];
        }

        try {
            // Auto-assign sort_order = MAX + 1
            $maxRows = Database::query('SELECT COALESCE(MAX(`sort_order`), -1) AS m FROM `resources` WHERE `tenant_id` = ?', [$tenantId]);
            $nextOrder = ((int) $maxRows[0]['m']) + 1;

            Database::execute(
                'INSERT INTO `resources`
                 (`id`, `tenant_id`, `name`, `description`, `capacity`, `price_per_night`,
                  `min_stay_nights`, `max_stay_nights`, `amenities`, `sort_order`, `cover_image_path`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $tenantId, $name, $description, $capacity, $priceValue, $minStay, $maxStay, json_encode($amenities), $nextOrder, $coverPath]
            );
        } catch (\Throwable $e) {
            if ($coverPath) {
                ImageUpload::delete($coverPath);
            }
            FormState::toast('error', __('admin.common.error_generic'));
            return Response::redirect("/admin/tenants/{$tenantId}/resources/create");
        }

        AuditLog::log('resource.created', 'resource', $id, [
            'tenant_id' => $tenantId,
            'name'      => $name,
        ]);

        FormState::clear();
        FormState::toast('success', __('admin.resources.created'));
        return Response::redirect("/admin/tenants/{$tenantId}/resources");
    }

    // ── Edit form ──

    public function edit(Request $request): Response
    {
        $tenantId   = $request->getAttribute('tenant_id');
        $resourceId = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        if (!$this->isResource($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $resource = $this->loadResource($resourceId, $tenantId);
        if ($resource === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/resources");
        }

        $resource['amenities'] = $resource['amenities'] ? json_decode($resource['amenities'], true) : [];

        // Load seasonal pricing entries
        $seasonalPricing = Database::query(
            'SELECT * FROM `seasonal_pricing`
             WHERE `resource_id` = ? AND `tenant_id` = ?
             ORDER BY `start_date` ASC',
            [$resourceId, $tenantId]
        );

        return $this->render('admin.tenants.resources.edit', __('admin.resources.edit'), [
            'documentTitle'   => $resource['name'] . ' — ' . __('admin.resources.edit'),
            'tenant'          => $tenant,
            'resource'        => $resource,
            'seasonalPricing' => $seasonalPricing,
            'old' => FormState::old(),
            'flash'           => FormState::getToast(),
        ], $tenantId);
    }

    // ── Update ──

    public function update(Request $request): Response
    {
        $tenantId   = $request->getAttribute('tenant_id');
        $resourceId = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isResource($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $resource = $this->loadResource($resourceId, $tenantId);
        if ($resource === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/resources");
        }

        $name        = trim($request->string('name'));
        $description = trim($request->string('description')) ?: null;
        $capacity    = max(1, (int) $request->string('capacity'));
        $price       = trim($request->string('price_per_night'));
        $minStay     = max(1, (int) $request->string('min_stay_nights'));
        $maxStay     = max(1, (int) $request->string('max_stay_nights'));
        $amenitiesRaw = trim($request->string('amenities'));

        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.resources.error_name_required');
        }

        $priceValue = null;
        if ($price !== '') {
            $priceValue = filter_var($price, FILTER_VALIDATE_FLOAT);
            if ($priceValue === false || $priceValue < 0) {
                $errors[] = __('admin.resources.error_price_invalid');
                $priceValue = null;
            }
        }

        if ($maxStay < $minStay) {
            $errors[] = __('admin.resources.error_stay_range');
        }

        if (!empty($errors)) {
            FormState::toast('error', implode(' ', $errors));
            FormState::flashInput([
                'name'            => $request->string('name'),
                'description'     => $request->string('description'),
                'capacity'        => $request->string('capacity'),
                'price_per_night' => $request->string('price_per_night'),
                'min_stay_nights' => $request->string('min_stay_nights'),
                'max_stay_nights' => $request->string('max_stay_nights'),
                'amenities'       => $request->string('amenities'),
            ]);
            return Response::redirect("/admin/tenants/{$tenantId}/resources/{$resourceId}/edit");
        }

        $amenities = $this->parseAmenities($amenitiesRaw);

        // Validate seasonal pricing overlaps BEFORE persisting
        $overlapError = $this->validateSeasonalPricing();
        if ($overlapError !== null) {
            $errors[] = $overlapError;
        }

        if (!empty($errors)) {
            FormState::toast('error', implode(' ', $errors));
            FormState::flashInput([
                'name'            => $request->string('name'),
                'description'     => $request->string('description'),
                'capacity'        => $request->string('capacity'),
                'price_per_night' => $request->string('price_per_night'),
                'min_stay_nights' => $request->string('min_stay_nights'),
                'max_stay_nights' => $request->string('max_stay_nights'),
                'amenities'       => $request->string('amenities'),
            ]);
            return Response::redirect("/admin/tenants/{$tenantId}/resources/{$resourceId}/edit");
        }

        // Handle cover image upload / removal
        $coverPath = $resource['cover_image_path'] ?? null;
        $removeCover = ($request->string('remove_cover_image') === '1');

        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $upload = ImageUpload::store('cover', $_FILES['cover_image'], $tenant['slug'], $coverPath);
            if ($upload['error']) {
                FormState::toast('error', $upload['error']);
                FormState::flashInput([
                'name'            => $request->string('name'),
                'description'     => $request->string('description'),
                'capacity'        => $request->string('capacity'),
                'price_per_night' => $request->string('price_per_night'),
                'min_stay_nights' => $request->string('min_stay_nights'),
                'max_stay_nights' => $request->string('max_stay_nights'),
                'amenities'       => $request->string('amenities'),
            ]);
                return Response::redirect("/admin/tenants/{$tenantId}/resources/{$resourceId}/edit");
            }
            $coverPath = $upload['path'];
        } elseif ($removeCover && $coverPath) {
            ImageUpload::delete($coverPath);
            $coverPath = null;
        }

        Database::execute(
            'UPDATE `resources` SET
                `name` = ?, `description` = ?, `capacity` = ?,
                `price_per_night` = ?, `min_stay_nights` = ?,
                `max_stay_nights` = ?, `amenities` = ?,
                `cover_image_path` = ?
             WHERE `id` = ? AND `tenant_id` = ?',
            [$name, $description, $capacity, $priceValue, $minStay, $maxStay, json_encode($amenities), $coverPath, $resourceId, $tenantId]
        );

        // Sync seasonal pricing: delete all, re-insert from form
        $this->syncSeasonalPricing($resourceId, $tenantId, $request);

        AuditLog::log('resource.updated', 'resource', $resourceId, [
            'tenant_id' => $tenantId,
            'name'      => $name,
        ]);

        FormState::clear();
        FormState::toast('success', __('admin.resources.updated'));
        return Response::redirect("/admin/tenants/{$tenantId}/resources");
    }

    // ── Activate ──

    public function activate(Request $request): Response
    {
        $tenantId   = $request->getAttribute('tenant_id');
        $resourceId = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isResource($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $resource = $this->loadResource($resourceId, $tenantId);
        if ($resource === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/resources");
        }

        Database::execute(
            'UPDATE `resources` SET `is_active` = 1 WHERE `id` = ? AND `tenant_id` = ?',
            [$resourceId, $tenantId]
        );

        AuditLog::log('resource.activated', 'resource', $resourceId, [
            'tenant_id' => $tenantId,
        ]);

        FormState::toast('success', __('admin.resources.activated'));
        return Response::redirect("/admin/tenants/{$tenantId}/resources");
    }

    // ── Deactivate ──

    public function deactivate(Request $request): Response
    {
        $tenantId   = $request->getAttribute('tenant_id');
        $resourceId = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null || !$this->isResource($tenant)) {
            return Response::redirect("/admin/tenants/{$tenantId}");
        }

        $resource = $this->loadResource($resourceId, $tenantId);
        if ($resource === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/resources");
        }

        Database::execute(
            'UPDATE `resources` SET `is_active` = 0 WHERE `id` = ? AND `tenant_id` = ?',
            [$resourceId, $tenantId]
        );

        AuditLog::log('resource.deactivated', 'resource', $resourceId, [
            'tenant_id' => $tenantId,
        ]);

        FormState::toast('success', __('admin.resources.deactivated'));
        return Response::redirect("/admin/tenants/{$tenantId}/resources");
    }

    // ── Reorder (move up / move down) ──

    public function reorder(Request $request): Response
    {
        $tenantId   = $request->getAttribute('tenant_id');
        $resourceId = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $direction = $request->string('direction');
        if (!in_array($direction, ['up', 'down'], true)) {
            return Response::redirect("/admin/tenants/{$tenantId}/resources");
        }

        $resource = $this->loadResource($resourceId, $tenantId);
        if ($resource === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/resources");
        }

        $currentActive = (int) $resource['is_active'];

        Database::transaction(function () use ($resourceId, $tenantId, $currentActive, $direction) {
            // Step 1: Normalize sort_order to sequential 0,1,2,… within the active group.
            $siblings = Database::query(
                'SELECT `id` FROM `resources`
                 WHERE `tenant_id` = ? AND `is_active` = ?
                 ORDER BY `sort_order` ASC, `name` ASC',
                [$tenantId, $currentActive]
            );
            foreach ($siblings as $i => $row) {
                Database::execute(
                    'UPDATE `resources` SET `sort_order` = ? WHERE `id` = ? AND `tenant_id` = ?',
                    [$i, $row['id'], $tenantId]
                );
            }

            // Step 2: Re-read normalized sort_order
            $fresh = Database::query(
                'SELECT `sort_order` FROM `resources` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
                [$resourceId, $tenantId]
            );
            $currentOrder = (int) $fresh[0]['sort_order'];

            // Step 3: Find adjacent sibling and swap
            if ($direction === 'up') {
                $rows = Database::query(
                    'SELECT `id`, `sort_order` FROM `resources`
                     WHERE `tenant_id` = ? AND `is_active` = ? AND `sort_order` < ?
                     ORDER BY `sort_order` DESC LIMIT 1',
                    [$tenantId, $currentActive, $currentOrder]
                );
            } else {
                $rows = Database::query(
                    'SELECT `id`, `sort_order` FROM `resources`
                     WHERE `tenant_id` = ? AND `is_active` = ? AND `sort_order` > ?
                     ORDER BY `sort_order` ASC LIMIT 1',
                    [$tenantId, $currentActive, $currentOrder]
                );
            }

            if (!empty($rows)) {
                $sibling = $rows[0];
                Database::execute(
                    'UPDATE `resources` SET `sort_order` = ? WHERE `id` = ? AND `tenant_id` = ?',
                    [(int) $sibling['sort_order'], $resourceId, $tenantId]
                );
                Database::execute(
                    'UPDATE `resources` SET `sort_order` = ? WHERE `id` = ? AND `tenant_id` = ?',
                    [$currentOrder, $sibling['id'], $tenantId]
                );
            }
        });

        return Response::redirect("/admin/tenants/{$tenantId}/resources");
    }

    // ── Helpers ──

    private function canAccess(string $tenantId): bool
    {
        return Auth::canAccessTenant($tenantId) && Auth::canManageTenant();
    }

    private function isResource(?array $tenant): bool
    {
        return ($tenant['booking_pattern'] ?? '') === 'resource';
    }

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT `id`, `name`, `slug`, `email`, `booking_pattern`, `currency`,
                    `timezone`, `min_advance_hours`, `max_advance_days`
             FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );
        return $rows[0] ?? null;
    }

    private function loadResource(string $resourceId, string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `resources` WHERE `id` = ? AND `tenant_id` = ? LIMIT 1',
            [$resourceId, $tenantId]
        );
        return $rows[0] ?? null;
    }

    /**
     * Parse comma-separated amenities string into a clean array.
     *
     * @return list<string>
     */
    private function parseAmenities(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn(string $tag) => $tag !== ''
        ));
    }

    /**
     * Sync seasonal pricing entries from POST data.
     *
     * Expects arrays: seasonal_start[], seasonal_end[], seasonal_price[], seasonal_label[]
     */
    private function syncSeasonalPricing(string $resourceId, string $tenantId, Request $request): void
    {
        // Delete existing
        Database::execute(
            'DELETE FROM `seasonal_pricing` WHERE `resource_id` = ? AND `tenant_id` = ?',
            [$resourceId, $tenantId]
        );

        $starts = $_POST['seasonal_start'] ?? [];
        $ends   = $_POST['seasonal_end'] ?? [];
        $prices = $_POST['seasonal_price'] ?? [];
        $labels = $_POST['seasonal_label'] ?? [];

        if (!is_array($starts)) {
            return;
        }

        foreach ($starts as $i => $start) {
            $start = trim($start);
            $end   = trim($ends[$i] ?? '');
            $price = trim($prices[$i] ?? '');
            $label = trim($labels[$i] ?? '') ?: null;

            if ($start === '' || $end === '' || $price === '') {
                continue;
            }

            $priceVal = filter_var($price, FILTER_VALIDATE_FLOAT);
            if ($priceVal === false || $priceVal < 0) {
                continue;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                continue;
            }

            if ($end < $start) {
                continue;
            }

            $id = Ulid::generate();
            Database::execute(
                'INSERT INTO `seasonal_pricing`
                 (`id`, `resource_id`, `tenant_id`, `start_date`, `end_date`, `price_per_night`, `label`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $resourceId, $tenantId, $start, $end, $priceVal, $label]
            );
        }
    }

    /**
     * Validate seasonal pricing form data for overlapping date ranges.
     *
     * Returns an error message if overlaps are found, null otherwise.
     */
    private function validateSeasonalPricing(): ?string
    {
        $starts = $_POST['seasonal_start'] ?? [];
        $ends   = $_POST['seasonal_end'] ?? [];

        if (!is_array($starts)) {
            return null;
        }

        $ranges = [];
        foreach ($starts as $i => $start) {
            $start = trim($start);
            $end   = trim($ends[$i] ?? '');

            if ($start === '' || $end === '') {
                continue;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                continue;
            }

            if ($end < $start) {
                continue;
            }

            // Check against previously collected ranges
            foreach ($ranges as [$rStart, $rEnd]) {
                if ($start <= $rEnd && $end >= $rStart) {
                    return __('admin.resources.error_seasonal_overlap');
                }
            }

            $ranges[] = [$start, $end];
        }

        return null;
    }

    private function render(string $template, string $pageTitle, array $extra, string $tenantId): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'resources',
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
