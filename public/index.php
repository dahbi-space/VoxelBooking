<?php

declare(strict_types=1);

/**
 * VoxelBooking Front Controller.
 *
 * All requests are routed through this file via .htaccess (Apache)
 * or server configuration (Nginx). Boots the application and dispatches.
 */

$app = require __DIR__ . '/../app/bootstrap.php';

// Register routes
$routeDefinitions = require __DIR__ . '/../app/routes.php';
$app->routes($routeDefinitions);

// Dispatch the request
$app->handle();
