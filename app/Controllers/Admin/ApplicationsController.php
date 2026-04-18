<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\FormState;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Business application review surface — operator-only.
 *
 * Lists, approves, and rejects access requests submitted via the
 * public landing page. Approval does not auto-create tenants;
 * operators manually create them via the existing tenant creation flow.
 */
final class ApplicationsController
{
    /**
     * GET /admin/applications — list all applications with status filter.
     */
    public function index(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return Response::html('Forbidden', 403);
        }

        $statusFilter = $request->string('status') ?: 'pending';
        $validStatuses = ['pending', 'approved', 'rejected', 'all'];
        if (!in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = 'pending';
        }

        if ($statusFilter === 'all') {
            $applications = Database::query(
                'SELECT * FROM `business_applications` ORDER BY `created_at` DESC LIMIT 200'
            );
        } else {
            $applications = Database::query(
                'SELECT * FROM `business_applications` WHERE `status` = ? ORDER BY `created_at` DESC LIMIT 200',
                [$statusFilter]
            );
        }

        // Pending count for badge
        $pendingCount = Database::query(
            "SELECT COUNT(*) AS `cnt` FROM `business_applications` WHERE `status` = 'pending'"
        )[0]['cnt'] ?? 0;

        return View::response('admin.applications.index', [
            'user'          => Auth::user(),
            'version'       => Version::get(),
            'pageTitle'     => __('admin.applications.title'),
            'documentTitle' => __('admin.applications.title'),
            'activePage'    => 'applications',
            'csrfToken'     => CsrfMiddleware::generateToken(),
            'applications'  => $applications,
            'statusFilter'  => $statusFilter,
            'pendingCount'  => (int) $pendingCount,
            'flash'         => FormState::getToast(),
        ]);
    }

    /**
     * POST /admin/applications/{id}/approve
     */
    public function approve(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return Response::html('Forbidden', 403);
        }

        $id = $request->getAttribute('id');
        $app = $this->findApplication($id);

        if (!$app) {
            FormState::toast('error', __('admin.applications.not_found'));
            return Response::redirect('/admin/applications');
        }

        if ($app['status'] !== 'pending') {
            FormState::toast('error', __('admin.applications.already_reviewed'));
            return Response::redirect('/admin/applications');
        }

        Database::execute(
            "UPDATE `business_applications` SET `status` = 'approved', `reviewed_at` = ?, `reviewed_by` = ? WHERE `id` = ?",
            [date('Y-m-d H:i:s'), Auth::user()['id'] ?? null, $id]
        );

        AuditLog::log('application.approved', 'business_application', $id, [
            'business_name' => $app['business_name'],
            'email'         => $app['email'],
        ]);

        FormState::toast('success', __('admin.applications.approved', ['name' => $app['business_name']]));
        return Response::redirect('/admin/applications');
    }

    /**
     * POST /admin/applications/{id}/reject
     */
    public function reject(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return Response::html('Forbidden', 403);
        }

        $id = $request->getAttribute('id');
        $app = $this->findApplication($id);

        if (!$app) {
            FormState::toast('error', __('admin.applications.not_found'));
            return Response::redirect('/admin/applications');
        }

        if ($app['status'] !== 'pending') {
            FormState::toast('error', __('admin.applications.already_reviewed'));
            return Response::redirect('/admin/applications');
        }

        $notes = trim($request->string('admin_notes')) ?: null;

        Database::execute(
            "UPDATE `business_applications` SET `status` = 'rejected', `admin_notes` = ?, `reviewed_at` = ?, `reviewed_by` = ? WHERE `id` = ?",
            [$notes, date('Y-m-d H:i:s'), Auth::user()['id'] ?? null, $id]
        );

        AuditLog::log('application.rejected', 'business_application', $id, [
            'business_name' => $app['business_name'],
            'email'         => $app['email'],
        ]);

        FormState::toast('success', __('admin.applications.rejected', ['name' => $app['business_name']]));
        return Response::redirect('/admin/applications');
    }

    private function findApplication(string $id): ?array
    {
        $rows = Database::query(
            'SELECT * FROM `business_applications` WHERE `id` = ? LIMIT 1',
            [$id]
        );
        return $rows[0] ?? null;
    }
}
