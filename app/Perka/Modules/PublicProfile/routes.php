<?php

declare(strict_types=1);

/**
 * PublicProfile module routes.
 *
 * Loaded by app/Perka/Routes.php with the shared $router in scope, already
 * inside the global-middleware group. The public profile page needs global
 * middleware only (Security/Installed/Demo/Throttle/Csrf) and is intentionally
 * NOT wrapped in AuthMiddleware — it is a public, unauthenticated page.
 *
 * @var \App\Engine\Router $router
 */

// ── Public (global middleware only, NOT authenticated) ──
$router->get(
    '/business/{slug}',
    \App\Perka\Modules\PublicProfile\Controllers\PublicProfileController::class,
    'show'
);

// ── Admin (reuse core AuthMiddleware) ──
// The {tenant_id} param name is REQUIRED verbatim: AuthMiddleware keys the
// business-user tenant→403 rule off exactly that attribute name.
$router->group([\App\Middleware\AuthMiddleware::class], function (\App\Engine\Router $router): void {
    $admin = \App\Perka\Modules\PublicProfile\Controllers\Admin\ProfileController::class;

    $router->get('/admin/tenants/{tenant_id}/profile', $admin, 'edit');
    $router->post('/admin/tenants/{tenant_id}/profile', $admin, 'save');
    $router->post('/admin/tenants/{tenant_id}/profile/publish', $admin, 'publish');
    $router->post('/admin/tenants/{tenant_id}/profile/unpublish', $admin, 'unpublish');
});
