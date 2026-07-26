<?php

declare(strict_types=1);

/**
 * Perka bootstrap orchestrator.
 *
 * Included by the core app/bootstrap.php through the frozen Phase 0 seam, AFTER
 * $app->boot() (so core services are up) and BEFORE the app is returned. The
 * core App instance is available here as $app.
 *
 * Responsibility (2a): discover modules and run each module's optional
 * bootstrap.php. Kept intentionally light — a single glob, no per-request heavy
 * work. Modules that need startup wiring add their own Modules/<Module>/
 * bootstrap.php; PublicProfile needs none for the public read page.
 *
 * @var \App\Engine\App $app
 */

foreach (glob(__DIR__ . '/Modules/*/bootstrap.php') ?: [] as $moduleBootstrap) {
    require $moduleBootstrap;
}
