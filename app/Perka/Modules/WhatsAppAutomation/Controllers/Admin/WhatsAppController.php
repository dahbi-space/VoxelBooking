<?php

declare(strict_types=1);

namespace App\Perka\Modules\WhatsAppAutomation\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\FormState;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Perka\Modules\WhatsAppAutomation\Services\WhatsAppAutomationService;
use App\Perka\Shared\PerkaView;

/**
 * Admin editor for a tenant's WhatsApp automation profile.
 *
 * Routes (registered in the module routes.php inside a Perka AuthMiddleware
 * group, so the {tenant_id} tenant→403 rule already applies to business users):
 *   GET  /admin/tenants/{tenant_id}/whatsapp   edit()
 *   POST /admin/tenants/{tenant_id}/whatsapp   save()
 *
 * Access: operator or owner (Auth::canManageTenant()); managers get 403 —
 * mirroring ProfileController::canAccess() / TenantSettingsController. This is
 * defence-in-depth on top of AuthMiddleware (which already blocks cross-tenant
 * business users).
 *
 * Modelled one-to-one on PublicProfile\Controllers\Admin\ProfileController,
 * minus image uploads and publish routes (this module has neither).
 */
final class WhatsAppController
{
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

        $profile = (new WhatsAppAutomationService())->getForTenant($tenantId);

        $body = PerkaView::render('WhatsAppAutomation', 'admin/whatsapp-edit', [
            'tenantId'  => $tenantId,
            'tenant'    => $tenant,
            'profile'   => $profile,
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

        $redirect = "/admin/tenants/{$tenantId}/whatsapp";

        // ── Fields ──
        $instance  = trim($request->string('whatsapp_instance'));
        $knowledge = trim($request->string('business_knowledge')) ?: null;
        // Unchecked checkboxes are absent from the POST body → inactive.
        $isActive  = $request->string('is_active') !== '' ? 1 : 0;

        // Instance is the lookup key for the whole module — required.
        if ($instance === '') {
            FormState::flashInput($this->formSnapshot($instance, $knowledge, $isActive));
            FormState::toast('error', 'WhatsApp instance name is required.');
            return Response::redirect($redirect);
        }

        // ── Persist ──
        try {
            (new WhatsAppAutomationService())->save($tenantId, [
                'whatsapp_instance'  => $instance,
                'business_knowledge' => $knowledge,
                'is_active'          => $isActive,
            ]);
        } catch (\Throwable $e) {
            // The instance name is globally UNIQUE; a collision with another
            // tenant surfaces as a duplicate-key error. Present it as a friendly
            // field error rather than a 500, and fall back to a generic message
            // for anything else.
            $message = $this->isDuplicate($e)
                ? 'That WhatsApp instance name is already in use. Choose a different one.'
                : 'Could not save the WhatsApp profile. Please try again.';

            FormState::flashInput($this->formSnapshot($instance, $knowledge, $isActive));
            FormState::toast('error', $message);
            return Response::redirect($redirect);
        }

        AuditLog::log('perka.whatsapp_updated', 'tenant', $tenantId, [
            'active' => $isActive,
        ], $tenantId);

        FormState::toast('success', 'WhatsApp profile saved.');
        return Response::redirect($redirect);
    }

    // ── Helpers ──

    /**
     * @return array<string, mixed>
     */
    private function formSnapshot(string $instance, ?string $knowledge, int $isActive): array
    {
        return [
            'whatsapp_instance'  => $instance,
            'business_knowledge' => $knowledge ?? '',
            'is_active'          => $isActive ? '1' : '',
        ];
    }

    /**
     * Detect a UNIQUE-constraint violation (duplicate whatsapp_instance).
     */
    private function isDuplicate(\Throwable $e): bool
    {
        // MySQL duplicate-entry is SQLSTATE 23000 / driver code 1062.
        return str_contains($e->getMessage(), '1062')
            || str_contains($e->getMessage(), 'Duplicate entry');
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
            'pageTitle'     => 'WhatsApp Automation',
            'documentTitle' => 'WhatsApp Automation — ' . ($tenant['name'] ?? ''),
            'activePage'    => 'perka-whatsapp',
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
