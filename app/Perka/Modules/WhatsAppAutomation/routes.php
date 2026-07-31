<?php

declare(strict_types=1);

/**
 * WhatsAppAutomation module routes.
 *
 * Loaded by app/Perka/Routes.php with the shared $router in scope, already
 * inside the global-middleware group (Security → Installed → Demo → Throttle →
 * Csrf) and after all core routes.
 *
 * Two independent surfaces:
 *   1. A machine-to-machine read endpoint for an external n8n workflow, guarded
 *      by the module's own shared-secret N8nApiKeyMiddleware (NOT tenant auth).
 *   2. An operator/owner admin editor, wrapped in the core AuthMiddleware (the
 *      {tenant_id} param name is REQUIRED verbatim — AuthMiddleware keys its
 *      business-user tenant→403 rule off exactly that attribute name).
 *
 * @var \App\Engine\Router $router
 */

// ── n8n machine-to-machine API (shared-secret X-API-Key, NOT tenant auth) ──
$router->group([\App\Perka\Modules\WhatsAppAutomation\Middleware\N8nApiKeyMiddleware::class], function (\App\Engine\Router $router): void {
    $router->get(
        '/api/whatsapp/profile',
        \App\Perka\Modules\WhatsAppAutomation\Controllers\Api\WhatsAppProfileController::class,
        'show'
    );
});

// ── Admin (reuse core AuthMiddleware) ──
$router->group([\App\Middleware\AuthMiddleware::class], function (\App\Engine\Router $router): void {
    $admin = \App\Perka\Modules\WhatsAppAutomation\Controllers\Admin\WhatsAppController::class;

    $router->get('/admin/tenants/{tenant_id}/whatsapp', $admin, 'edit');
    $router->post('/admin/tenants/{tenant_id}/whatsapp', $admin, 'save');
});
