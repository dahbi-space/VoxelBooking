<?php

declare(strict_types=1);

/**
 * Perka platform routes (cross-module).
 *
 * Loaded by app/Perka/Routes.php with the shared $router in scope, already
 * inside the global-middleware group. These are operator-facing admin routes,
 * so they wrap in core AuthMiddleware; the operator-only check itself is
 * enforced inside the controller (AuthMiddleware::OPERATOR_ONLY_PREFIXES is a
 * core const and is intentionally not modified).
 *
 * @var \App\Engine\Router $router
 */

$router->group([\App\Middleware\AuthMiddleware::class], function (\App\Engine\Router $router): void {
    $updates = \App\Perka\Platform\Controllers\UpdatesController::class;

    $router->get('/admin/perka/updates', $updates, 'index');
    $router->post('/admin/perka/updates/run', $updates, 'run');
});
