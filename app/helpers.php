<?php

declare(strict_types=1);

/**
 * Global helper functions.
 *
 * Available in all templates and controllers via Composer autoload.
 */

use App\Engine\View;

/**
 * Escape HTML output.
 */
function e(mixed $value): string
{
    return View::e($value);
}

/**
 * Get environment variable.
 */
function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

/**
 * Get the application URL.
 */
function app_url(string $path = ''): string
{
    $base = rtrim(env('APP_URL', ''), '/');

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

/**
 * Get path to a compiled asset (reads Vite manifest in production).
 */
function asset(string $path): string
{
    return app_url('assets/' . ltrim($path, '/'));
}
