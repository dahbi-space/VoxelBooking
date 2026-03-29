<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Models\Customer;

/**
 * Customer list & detail controller (tenant-scoped).
 *
 * Access: all authenticated admin roles (operator, owner, manager).
 * Per PRD Roles Checklist: managers have operational access to customers.
 *
 * GET /admin/tenants/{tenant_id}/customers          → list
 * GET /admin/tenants/{tenant_id}/customers/{id}     → detail + booking history
 */
final class CustomersController
{
    private const PER_PAGE = 25;

    // ── List ──

    public function index(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!Auth::canAccessTenant($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $search = $request->string('search') ?: null;
        $page   = max(1, (int) ($request->string('page') ?: 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $customers = Customer::forTenant($tenantId, $search, self::PER_PAGE, $offset);
        $total     = Customer::countForTenant($tenantId, $search);

        return $this->render('admin.tenants.customers.index', __('admin.customers.page_title'), [
            'documentTitle' => __('admin.customers.page_title') . ' — ' . $tenant['name'],
            'tenant'    => $tenant,
            'customers' => $customers,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => self::PER_PAGE,
            'search'    => $search ?? '',
        ], $tenantId);
    }

    // ── Detail ──

    public function show(Request $request): Response
    {
        $tenantId   = $request->getAttribute('tenant_id');
        $customerId = $request->getAttribute('id');

        if (!Auth::canAccessTenant($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $customer = Customer::find($customerId);
        if ($customer === null || $customer['tenant_id'] !== $tenantId) {
            return Response::redirect("/admin/tenants/{$tenantId}/customers");
        }

        $bookings = Customer::bookingsForCustomer($customerId);

        return $this->render('admin.tenants.customers.show', $customer['name'], [
            'documentTitle' => $customer['name'] . ' — ' . __('admin.customers.page_title'),
            'tenant'   => $tenant,
            'customer' => $customer,
            'bookings' => $bookings,
        ], $tenantId);
    }

    // ── Helpers ──

    private function loadTenant(string $tenantId): ?array
    {
        $rows = \App\Engine\Database::query(
            'SELECT `id`, `name`, `slug`, `email`, `timezone`, `currency` FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );

        return $rows[0] ?? null;
    }

    private function render(string $template, string $pageTitle, array $extra, string $tenantId): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'customers',
            'csrfToken'  => CsrfMiddleware::generateToken(),
            'tenantId'   => $tenantId,
        ], $extra));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Access denied.',
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
