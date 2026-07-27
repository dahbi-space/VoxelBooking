<?php

declare(strict_types=1);

namespace App\Perka\Modules\PublicProfile\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\FormState;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Perka\Modules\PublicProfile\Models\Review;
use App\Perka\Shared\PerkaView;

/**
 * Admin curation for a tenant's public reviews (testimonials).
 *
 * Routes (registered in the module routes.php inside the Perka AuthMiddleware
 * group, so the {tenant_id} tenant→403 rule already applies to business users):
 *   GET  /admin/tenants/{tenant_id}/profile/reviews              index()
 *   POST /admin/tenants/{tenant_id}/profile/reviews              store()
 *   POST /admin/tenants/{tenant_id}/profile/reviews/{id}         update()
 *   POST /admin/tenants/{tenant_id}/profile/reviews/{id}/publish togglePublish()
 *   POST /admin/tenants/{tenant_id}/profile/reviews/{id}/delete  destroy()
 *
 * Access: operator or owner (Auth::canManageTenant()); managers get 403 —
 * the exact guard used by the sibling ProfileController. This is the ONLY write
 * path to perka_reviews; there is no customer-facing "leave a review" form.
 *
 * Every mutation re-verifies the target review belongs to {tenant_id} before
 * touching it, so a valid CSRF token for one tenant can never edit another's
 * reviews.
 */
final class ReviewsController
{
    public function index(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $body = PerkaView::render('PublicProfile', 'admin/reviews', [
            'tenantId'  => $tenantId,
            'tenant'    => $tenant,
            'reviews'   => Review::allForTenant($tenantId),
            'csrfToken' => CsrfMiddleware::generateToken(),
        ]);

        return $this->shell($body, $tenant);
    }

    public function store(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }
        if ($this->loadTenant($tenantId) === null) {
            return Response::redirect('/admin/tenants');
        }

        $redirect = "/admin/tenants/{$tenantId}/profile/reviews";

        [$data, $error] = $this->validate($request);
        if ($error !== null) {
            FormState::toast('error', $error);
            return Response::redirect($redirect);
        }

        $id = Review::create($tenantId, $data);
        AuditLog::log('perka.review_created', 'tenant', $tenantId, ['review' => $id], $tenantId);

        FormState::toast('success', 'Review added.');
        return Response::redirect($redirect);
    }

    public function update(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $redirect = "/admin/tenants/{$tenantId}/profile/reviews";

        $review = $this->ownedReview($request, $tenantId);
        if ($review === null) {
            FormState::toast('error', 'Review not found.');
            return Response::redirect($redirect);
        }

        [$data, $error] = $this->validate($request);
        if ($error !== null) {
            FormState::toast('error', $error);
            return Response::redirect($redirect);
        }

        Review::update((string) $review['id'], $data);
        AuditLog::log('perka.review_updated', 'tenant', $tenantId, ['review' => $review['id']], $tenantId);

        FormState::toast('success', 'Review saved.');
        return Response::redirect($redirect);
    }

    public function togglePublish(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $redirect = "/admin/tenants/{$tenantId}/profile/reviews";

        $review = $this->ownedReview($request, $tenantId);
        if ($review === null) {
            FormState::toast('error', 'Review not found.');
            return Response::redirect($redirect);
        }

        $nowPublished = (int) ($review['is_published'] ?? 0) === 1 ? 0 : 1;
        Review::update((string) $review['id'], ['is_published' => $nowPublished]);
        AuditLog::log(
            $nowPublished === 1 ? 'perka.review_published' : 'perka.review_unpublished',
            'tenant',
            $tenantId,
            ['review' => $review['id']],
            $tenantId
        );

        FormState::toast('success', $nowPublished === 1 ? 'Review published.' : 'Review hidden.');
        return Response::redirect($redirect);
    }

    public function destroy(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $redirect = "/admin/tenants/{$tenantId}/profile/reviews";

        $review = $this->ownedReview($request, $tenantId);
        if ($review === null) {
            FormState::toast('error', 'Review not found.');
            return Response::redirect($redirect);
        }

        Review::delete((string) $review['id']);
        AuditLog::log('perka.review_deleted', 'tenant', $tenantId, ['review' => $review['id']], $tenantId);

        FormState::toast('success', 'Review deleted.');
        return Response::redirect($redirect);
    }

    // ── Helpers ──

    /**
     * Validate and shape review input from the request.
     *
     * @return array{0: array<string, mixed>, 1: string|null} [data, error]
     */
    private function validate(Request $request): array
    {
        $rating = $request->int('rating');
        if ($rating < 1 || $rating > 5) {
            return [[], 'Rating must be between 1 and 5 stars.'];
        }

        $body = trim($request->string('body'));
        if ($body === '') {
            return [[], 'Review text is required.'];
        }

        $reviewerName = trim($request->string('reviewer_name'));

        // reviewed_at accepts a date (or datetime); empty falls back to now so a
        // freshly added review sorts sensibly. Invalid input is rejected.
        $reviewedAtRaw = trim($request->string('reviewed_at'));
        if ($reviewedAtRaw === '') {
            $reviewedAt = date('Y-m-d H:i:s');
        } else {
            $ts = strtotime($reviewedAtRaw);
            if ($ts === false) {
                return [[], 'Review date is not a valid date.'];
            }
            $reviewedAt = date('Y-m-d H:i:s', $ts);
        }

        return [[
            'rating'        => $rating,
            'body'          => $body,
            'reviewer_name' => $reviewerName !== '' ? $reviewerName : null,
            'reviewed_at'   => $reviewedAt,
            'sort_order'    => $request->int('sort_order'),
            'is_published'  => $request->has('is_published') ? 1 : 0,
        ], null];
    }

    /**
     * Load the review named by {id} only if it belongs to this tenant.
     *
     * @return array<string, mixed>|null
     */
    private function ownedReview(Request $request, string $tenantId): ?array
    {
        $id = (string) $request->getAttribute('id');
        if ($id === '') {
            return null;
        }

        $review = Review::find($id);
        if ($review === null || (string) $review['tenant_id'] !== $tenantId) {
            return null;
        }

        return $review;
    }

    private function canAccess(string $tenantId): bool
    {
        return Auth::canAccessTenant($tenantId) && Auth::canManageTenant();
    }

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query('SELECT * FROM `tenants` WHERE `id` = ? LIMIT 1', [$tenantId]);
        return $rows[0] ?? null;
    }

    /**
     * Wrap module body HTML in the core admin shell (reused, not modified).
     */
    private function shell(string $content, array $tenant): Response
    {
        return View::response('admin.layout', [
            'user'          => Auth::user(),
            'version'       => Version::get(),
            'pageTitle'     => 'Reviews',
            'documentTitle' => 'Reviews — ' . ($tenant['name'] ?? ''),
            'activePage'    => 'perka-profile',
            'csrfToken'     => CsrfMiddleware::generateToken(),
            'tenantId'      => $tenant['id'] ?? '',
            'tenant'        => $tenant,
            'content'       => $content,
            'flash'         => FormState::getToast(),
        ]);
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Owner or operator access required.',
            ], 403);
        }

        try {
            return View::response('admin.errors.403', [
                'user'      => Auth::user(),
                'version'   => Version::get(),
                'pageTitle' => '403',
                'csrfToken' => CsrfMiddleware::generateToken(),
            ], 403);
        } catch (\Throwable) {
            return Response::html('<h1>403 Forbidden</h1><p>Access denied.</p>', 403);
        }
    }
}
