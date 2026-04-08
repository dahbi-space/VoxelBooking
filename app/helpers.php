<?php

declare(strict_types=1);

/**
 * Global helper functions.
 *
 * Available in all templates and controllers via Composer autoload.
 */

use App\Engine\Database;
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

/**
 * Get the configured application name.
 *
 * Reads app_name from the settings table (cached per-request).
 * Falls back to APP_NAME env var, then generic default.
 * Used for white-label support: operators set the name during installation
 * or in Admin → Settings → General.
 */
function app_name(): string
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    try {
        $rows = Database::query(
            "SELECT `value` FROM `settings` WHERE `key` = 'app_name' LIMIT 1"
        );
        if (!empty($rows) && !empty($rows[0]['value'])) {
            $cached = $rows[0]['value'];
            return $cached;
        }
    } catch (\Throwable) {
        // DB unavailable (pre-install, test context) — fall through
    }

    $cached = $_ENV['APP_NAME'] ?? 'Booking System';
    return $cached;
}

/**
 * Get the configured brand/vendor URL.
 *
 * Resolution order: DB setting → APP_URL env → default.
 * Used for white-label support: the "Powered by" footer link
 * can point to the operator's own domain instead of voxelbooking.com.
 */
function brand_url(): string
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    try {
        $rows = Database::query(
            "SELECT `value` FROM `settings` WHERE `key` = 'brand_url' LIMIT 1"
        );
        if (!empty($rows) && !empty($rows[0]['value'])) {
            $cached = $rows[0]['value'];
            return $cached;
        }
    } catch (\Throwable) {
        // DB unavailable (pre-install, test context) — fall through
    }

    $cached = $_ENV['BRAND_URL'] ?? 'https://voxelbooking.com';
    return $cached;
}

/**
 * Resolve the display label for a booking based on its pattern.
 *
 * Returns the most specific name available: event_name for event bookings,
 * resource_name for resource bookings, service_name for timeslot.
 * Capacity bookings typically have no service/resource/event — falls back
 * to a translated pattern label so shared surfaces never render '—'.
 */
function booking_display_label(array $booking): string
{
    $pattern = $booking['booking_pattern'] ?? 'timeslot';

    return match ($pattern) {
        'event'    => $booking['event_name'] ?? $booking['service_name'] ?? __('admin.bookings.capacity_booking'),
        'resource' => $booking['resource_name'] ?? $booking['service_name'] ?? '—',
        'capacity' => $booking['service_name'] ?? __('admin.bookings.capacity_booking'),
        default    => $booking['service_name'] ?? '—',
    };
}

/**
 * Get all supported currencies with their display labels in a unified array.
 *
 * Labels follow the format: {symbol} {ISO} — {translated name}.
 * The name portion is i18n-backed via admin.currencies.{code}.
 */
function get_supported_currencies(): array
{
    return [
        'EUR' => '€ EUR — ' . __('admin.currencies.EUR'),
        'USD' => '$ USD — ' . __('admin.currencies.USD'),
        'GBP' => '£ GBP — ' . __('admin.currencies.GBP'),
        'CHF' => 'CHF — ' . __('admin.currencies.CHF'),
        'SEK' => 'SEK — ' . __('admin.currencies.SEK'),
        'NOK' => 'NOK — ' . __('admin.currencies.NOK'),
        'DKK' => 'DKK — ' . __('admin.currencies.DKK'),
        'PLN' => 'PLN — ' . __('admin.currencies.PLN'),
        'CZK' => 'CZK — ' . __('admin.currencies.CZK'),
        'HUF' => 'HUF — ' . __('admin.currencies.HUF'),
        'RON' => 'RON — ' . __('admin.currencies.RON'),
        'BGN' => 'BGN — ' . __('admin.currencies.BGN'),
        'HRK' => 'HRK — ' . __('admin.currencies.HRK'),
        'CAD' => 'CAD — ' . __('admin.currencies.CAD'),
        'AUD' => 'AUD — ' . __('admin.currencies.AUD'),
        'NZD' => 'NZD — ' . __('admin.currencies.NZD'),
        'BRL' => 'BRL — ' . __('admin.currencies.BRL'),
        'MXN' => 'MXN — ' . __('admin.currencies.MXN'),
        'ARS' => 'ARS — ' . __('admin.currencies.ARS'),
        'JPY' => '¥ JPY — ' . __('admin.currencies.JPY'),
        'CNY' => '¥ CNY — ' . __('admin.currencies.CNY'),
        'KRW' => '₩ KRW — ' . __('admin.currencies.KRW'),
        'INR' => '₹ INR — ' . __('admin.currencies.INR'),
        'SGD' => 'SGD — ' . __('admin.currencies.SGD'),
        'THB' => '฿ THB — ' . __('admin.currencies.THB'),
        'IDR' => 'IDR — ' . __('admin.currencies.IDR'),
        'AED' => 'AED — ' . __('admin.currencies.AED'),
        'ZAR' => 'ZAR — ' . __('admin.currencies.ZAR'),
        'TRY' => '₺ TRY — ' . __('admin.currencies.TRY'),
        'ILS' => '₪ ILS — ' . __('admin.currencies.ILS'),
        'EGP' => 'EGP — ' . __('admin.currencies.EGP'),
        'NGN' => '₦ NGN — ' . __('admin.currencies.NGN'),
    ];
}

/**
 * Get all standard PHP timezones for consistent global availability.
 */
function get_supported_timezones(): array
{
    static $tzs = null;
    if ($tzs === null) {
        $tzs = timezone_identifiers_list();
    }
    return $tzs;
}

/**
 * Formats a raw timezone string ('America/New_York') into a clean display label ('America / New York').
 */
function format_timezone(string $tz): string
{
    if ($tz === 'UTC') return 'UTC';
    return str_replace(['_', '/'], [' ', ' / '], $tz);
}
