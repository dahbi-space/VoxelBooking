<?php

declare(strict_types=1);

/**
 * Perka route orchestrator.
 *
 * Included by the core app/routes.php through the frozen Phase 0 seam, from
 * INSIDE the global-middleware group (Security → Installed → Demo → Throttle →
 * Csrf) and AFTER all core routes — so $router is in scope with global
 * middleware applied, and Perka routes can never shadow a core route.
 *
 * Responsibility: discover modules and load each module's routes.php. Kept
 * light — a single glob, no per-request heavy work. Each module file registers
 * its own routes on the shared $router (public routes directly; admin routes
 * wrap themselves in their own AuthMiddleware group in later sub-phases).
 *
 * @var \App\Engine\Router $router
 */

foreach (glob(__DIR__ . '/Modules/*/routes.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}

// Platform-level (cross-module) routes, e.g. the Updates/migrator page.
if (is_file(__DIR__ . '/Platform/routes.php')) {
    require __DIR__ . '/Platform/routes.php';
}
