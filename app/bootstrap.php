<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Loads the Composer autoloader, creates and boots the App kernel.
 * This file is required by public/index.php.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \App\Engine\App(dirname(__DIR__));
$app->boot();

return $app;
