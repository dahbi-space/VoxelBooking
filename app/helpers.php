<?php

declare(strict_types=1);

/**
 * Global helper functions.
 *
 * Available in all templates and controllers via Composer autoload.
 */

use App\Engine\Locale;
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

// ════════════════════════════════════════════════════════════════
// Translation & Formatting Helpers
// ════════════════════════════════════════════════════════════════

/**
 * Translate a key with optional replacements.
 *
 * @param string $key     Dot-notation: 'booking.steps.service_title'
 * @param array  $replace Placeholder replacements: ['name' => 'John']
 */
function __(string $key, array $replace = []): string
{
    return Locale::translate($key, $replace);
}

/**
 * Pluralize a translation key based on count.
 */
function __p(string $key, int $count, array $replace = []): string
{
    return Locale::plural($key, $count, $replace);
}

/**
 * Format a number according to the active locale.
 */
function __n(float $value, int $decimals = 0): string
{
    return Locale::number($value, $decimals);
}

/**
 * Format a currency value according to the active locale.
 */
function __c(float $value, string $currency): string
{
    return Locale::currency($value, $currency);
}

/**
 * Format a date (short) according to the active locale.
 */
function __d(\DateTimeInterface $dt): string
{
    return Locale::date($dt);
}

/**
 * Format a date (long) according to the active locale.
 */
function __dl(\DateTimeInterface $dt): string
{
    return Locale::dateLong($dt);
}

/**
 * Format a time according to the active locale.
 */
function __t(\DateTimeInterface $dt): string
{
    return Locale::time($dt);
}
