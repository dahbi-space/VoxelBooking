<?php

declare(strict_types=1);

namespace App\Perka\Modules\PublicProfile\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\FormState;
use App\Engine\ImageUpload;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Perka\Modules\PublicProfile\Services\PublicProfileService;
use App\Perka\Shared\PerkaView;

/**
 * Admin editor for a tenant's public business profile.
 *
 * Routes (registered in the module routes.php inside a Perka AuthMiddleware
 * group, so the {tenant_id} tenant→403 rule already applies to business users):
 *   GET  /admin/tenants/{tenant_id}/profile           edit()
 *   POST /admin/tenants/{tenant_id}/profile           save()
 *   POST /admin/tenants/{tenant_id}/profile/publish   publish()
 *   POST /admin/tenants/{tenant_id}/profile/unpublish unpublish()
 *
 * Access: operator or owner (Auth::canManageTenant()); managers get 403 —
 * mirroring TenantSettingsController::canAccess(). This is defence-in-depth on
 * top of AuthMiddleware (which already blocks cross-tenant business users).
 *
 * Gallery images: stored via core ImageUpload under the tenant_id root
 * (uploads/{tenant_id}/perka/gallery/), NOT the slug — because tenants.slug is
 * mutable in VoxelBooking, and a stable id-based root keeps galleries from
 * being stranded on a future slug rename.
 */
final class ProfileController
{
    /** Social platforms exposed in the form. */
    private const SOCIAL_PLATFORMS = ['instagram', 'facebook', 'x', 'tiktok', 'youtube', 'website'];

    /** Allowed presentation themes. */
    private const THEMES = ['default', 'light', 'dark'];

    public function edit(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $profile = (new PublicProfileService())->getForTenant($tenantId);

        $body = PerkaView::render('PublicProfile', 'admin/profile-edit', [
            'tenantId'  => $tenantId,
            'tenant'    => $tenant,
            'profile'   => $profile,
            'themes'    => self::THEMES,
            'platforms' => self::SOCIAL_PLATFORMS,
            'csrfToken' => CsrfMiddleware::generateToken(),
        ]);

        return $this->shell($body, $tenant);
    }

    public function save(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $redirect = "/admin/tenants/{$tenantId}/profile";

        // ── Scalar fields ──
        $headline = trim($request->string('headline')) ?: null;
        $about    = trim($request->string('about')) ?: null;
        $seoTitle = trim($request->string('seo_title')) ?: null;
        $seoDesc  = trim($request->string('seo_description')) ?: null;

        $theme = trim($request->string('theme'));
        if (!in_array($theme, self::THEMES, true)) {
            $theme = 'default';
        }

        // ── Socials: normalise to https URLs, drop invalid ──
        $socials = [];
        foreach (self::SOCIAL_PLATFORMS as $platform) {
            $value = trim($request->string("social_{$platform}"));
            if ($value === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $value)) {
                $value = 'https://' . $value;
            }
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $socials[$platform] = $value;
            }
        }

        // ── Gallery: start from existing, apply removals, append uploads ──
        $existing = (new PublicProfileService())->getForTenant($tenantId);
        $originalGallery = is_array($existing['gallery'] ?? null) ? $existing['gallery'] : [];

        $all = $request->all();
        $removeRequested = isset($all['remove_gallery']) && is_array($all['remove_gallery'])
            ? $all['remove_gallery']
            : [];
        // Only ever delete paths that are actually in this tenant's gallery.
        $toRemove = array_values(array_intersect($originalGallery, $removeRequested));

        $gallery = array_values(array_filter(
            $originalGallery,
            static fn ($p) => !in_array($p, $toRemove, true)
        ));

        [$uploadedPaths, $uploadError] = $this->handleGalleryUploads($tenantId);
        if ($uploadError !== null) {
            foreach ($uploadedPaths as $p) {
                ImageUpload::delete($p);
            }
            FormState::flashInput($this->formSnapshot($headline, $about, $seoTitle, $seoDesc, $theme, $socials));
            FormState::toast('error', $uploadError);
            return Response::redirect($redirect);
        }

        $gallery = array_merge($gallery, $uploadedPaths);

        // ── Persist ──
        try {
            (new PublicProfileService())->save($tenantId, [
                'headline'        => $headline,
                'about'           => $about,
                'seo_title'       => $seoTitle,
                'seo_description' => $seoDesc,
                'theme'           => $theme,
                'socials'         => $socials,
                'gallery'         => $gallery,
            ]);
        } catch (\Throwable) {
            // Roll back freshly uploaded files; keep existing ones intact.
            foreach ($uploadedPaths as $p) {
                ImageUpload::delete($p);
            }
            FormState::flashInput($this->formSnapshot($headline, $about, $seoTitle, $seoDesc, $theme, $socials));
            FormState::toast('error', 'Could not save the profile. Please try again.');
            return Response::redirect($redirect);
        }

        // DB write succeeded — now delete removed files from disk.
        foreach ($toRemove as $p) {
            ImageUpload::delete($p);
        }

        AuditLog::log('perka.profile_updated', 'tenant', $tenantId, [
            'removed' => count($toRemove),
            'added'   => count($uploadedPaths),
        ], $tenantId);

        FormState::toast('success', 'Profile saved.');
        return Response::redirect($redirect);
    }

    public function publish(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }
        if ($this->loadTenant($tenantId) === null) {
            return Response::redirect('/admin/tenants');
        }

        (new PublicProfileService())->publish($tenantId);
        AuditLog::log('perka.profile_published', 'tenant', $tenantId, [], $tenantId);

        FormState::toast('success', 'Profile published.');
        return Response::redirect("/admin/tenants/{$tenantId}/profile");
    }

    public function unpublish(Request $request): Response
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }
        if ($this->loadTenant($tenantId) === null) {
            return Response::redirect('/admin/tenants');
        }

        (new PublicProfileService())->unpublish($tenantId);
        AuditLog::log('perka.profile_unpublished', 'tenant', $tenantId, [], $tenantId);

        FormState::toast('success', 'Profile unpublished.');
        return Response::redirect("/admin/tenants/{$tenantId}/profile");
    }

    // ── Helpers ──

    /**
     * Reshape and store any uploaded gallery files.
     *
     * @return array{0: list<string>, 1: string|null} [uploadedPaths, error]
     */
    private function handleGalleryUploads(string $tenantId): array
    {
        $files = $_FILES['gallery_images'] ?? null;
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
            return [[], null];
        }

        $uploaded = [];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $one = [
                'name'     => $files['name'][$i] ?? '',
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];

            // tenant_id root (slug is mutable — see class docblock).
            $result = ImageUpload::store('perka/gallery', $one, $tenantId);
            if ($result['error'] !== null) {
                return [$uploaded, $result['error']];
            }
            if ($result['path'] !== null) {
                $uploaded[] = $result['path'];
            }
        }

        return [$uploaded, null];
    }

    /**
     * @param array<string, string> $socials
     * @return array<string, mixed>
     */
    private function formSnapshot(
        ?string $headline,
        ?string $about,
        ?string $seoTitle,
        ?string $seoDesc,
        string $theme,
        array $socials
    ): array {
        $snapshot = [
            'headline'        => $headline ?? '',
            'about'           => $about ?? '',
            'seo_title'       => $seoTitle ?? '',
            'seo_description' => $seoDesc ?? '',
            'theme'           => $theme,
        ];
        foreach (self::SOCIAL_PLATFORMS as $platform) {
            $snapshot["social_{$platform}"] = $socials[$platform] ?? '';
        }

        return $snapshot;
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
            'pageTitle'     => 'Business Profile',
            'documentTitle' => 'Business Profile — ' . ($tenant['name'] ?? ''),
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
