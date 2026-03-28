<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Models\Booking;

/**
 * Booking management controller.
 *
 * Operator routes (cross-tenant): /admin/bookings
 * Tenant-context routes (business user + operator): /admin/tenants/{tenant_id}/bookings
 *
 * GET  /admin/bookings              → Cross-tenant list (operator-only)
 * GET  /admin/bookings/{id}         → Detail (operator-only)
 * POST /admin/bookings/{id}/status  → Update status (operator-only)
 * GET  /admin/tenants/{tenant_id}/bookings              → Per-tenant list
 * GET  /admin/tenants/{tenant_id}/bookings/{id}         → Per-tenant detail
 * POST /admin/tenants/{tenant_id}/bookings/{id}/status  → Per-tenant status update
 */
final class BookingsController
{
    private const PER_PAGE = 25; // PRD §1338

    // ── Operator cross-tenant routes ──

    public function index(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $status = $request->string('status') ?: null;
        $from   = $request->string('from') ?: null;
        $to     = $request->string('to') ?: null;
        $search = $request->string('search') ?: null;
        $page   = max(1, (int) ($request->string('page') ?: 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $bookings = Booking::all($status, $from, $to, $search, self::PER_PAGE, $offset);
        $total    = Booking::countAll($status, $from, $to, $search);

        return $this->render('admin.bookings.index', __('admin.bookings.title'), [
            'bookings'         => $bookings,
            'total'            => $total,
            'page'             => $page,
            'perPage'          => self::PER_PAGE,
            'showTenantColumn' => true,
            'filters'          => compact('status', 'from', 'to', 'search'),
            'flash'            => $this->flash(),
            'backUrl'          => '/admin/bookings',
        ]);
    }

    public function show(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $booking = Booking::find($request->getAttribute('id'));
        if ($booking === null) {
            return Response::redirect('/admin/bookings');
        }

        return $this->render('admin.bookings.show', __('admin.bookings.title'), [
            'documentTitle' => __('admin.bookings.detail_title'),
            'booking' => $booking,
            'backUrl' => '/admin/bookings',
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        return $this->doUpdateStatus($request, '/admin/bookings');
    }

    // ── Tenant-context routes (business users + operators) ──

    public function tenantIndex(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        // AuthMiddleware:59-66 already enforced tenant_id match for business users

        $status = $request->string('status') ?: null;
        $from   = $request->string('from') ?: null;
        $to     = $request->string('to') ?: null;
        $page   = max(1, (int) ($request->string('page') ?: 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $bookings = Booking::forTenant($tenantId, $status, $from, $to, self::PER_PAGE, $offset);
        $total    = Booking::countForTenant($tenantId, $status, $from, $to);

        return $this->render('admin.bookings.index', __('admin.bookings.title'), [
            'bookings'         => $bookings,
            'total'            => $total,
            'page'             => $page,
            'perPage'          => self::PER_PAGE,
            'showTenantColumn' => false,
            'tenantId'         => $tenantId,
            'filters'          => compact('status', 'from', 'to'),
            'flash'            => $this->flash(),
            'backUrl'          => "/admin/tenants/{$tenantId}/bookings",
        ]);
    }

    public function tenantShow(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $booking = Booking::find($request->getAttribute('id'));

        if ($booking === null || $booking['tenant_id'] !== $tenantId) {
            return Response::redirect("/admin/tenants/{$tenantId}/bookings");
        }

        return $this->render('admin.bookings.show', __('admin.bookings.title'), [
            'documentTitle' => __('admin.bookings.detail_title'),
            'booking' => $booking,
            'backUrl' => "/admin/tenants/{$tenantId}/bookings",
        ]);
    }

    public function tenantUpdateStatus(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $id = $request->getAttribute('id');

        // Guard: verify the booking belongs to this tenant before allowing mutation
        $booking = Booking::find($id);
        if ($booking === null || $booking['tenant_id'] !== $tenantId) {
            $this->setFlash('error', __('admin.bookings.flash_status_failed'));
            return Response::redirect("/admin/tenants/{$tenantId}/bookings");
        }

        return $this->doUpdateStatus($request, "/admin/tenants/{$tenantId}/bookings");
    }

    // ── Shared helpers ──

    private function doUpdateStatus(Request $request, string $redirectBase): Response
    {
        $id = $request->getAttribute('id');
        $newStatus = trim($request->string('status'));
        $booking = Booking::find($id);

        if ($booking === null) {
            $this->setFlash('error', __('admin.bookings.flash_status_failed'));
            return Response::redirect($redirectBase);
        }

        $oldStatus = $booking['status'];

        if (Booking::updateStatus($id, $newStatus)) {
            AuditLog::log('booking.status_changed', 'booking', $id, [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
            $this->setFlash('success', __('admin.bookings.flash_status_updated'));
        } else {
            $this->setFlash('error', __('admin.bookings.flash_status_failed'));
        }

        return Response::redirect("{$redirectBase}/{$id}");
    }

    private function render(string $template, string $pageTitle, array $extra = []): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'bookings',
            'csrfToken'  => CsrfMiddleware::generateToken(),
        ], $extra));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Operator access required.',
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
