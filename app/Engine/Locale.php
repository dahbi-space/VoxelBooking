<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Centralized i18n engine.
 *
 * Manages the active locale, loads translation files, and provides
 * formatting functions for dates, times, numbers, and currencies.
 *
 * Resolution order (booking page):
 *   1. Tenant locale_override (explicit lock, if set)
 *   2. Browser Accept-Language (best supported match)
 *   3. Tenant default locale
 *   4. Fallback: 'en'
 *
 * Resolution order (admin panel):
 *   1. Session preference
 *   2. Browser Accept-Language (first supported match)
 *   3. System default
 *   4. Fallback: 'en'
 *
 * Timezone policy:
 *   - Storage/availability: Tenant timezone (authoritative, never negotiated)
 *   - Display-local time:   Browser timezone (JS-side, via Intl.DateTimeFormat)
 */
final class Locale
{
    private static string $locale = 'en';
    private static string $fallback = 'en';
    private static array $registry = [];
    private static array $translations = [];
    private static string $basePath = '';

    /**
     * Initialize the locale engine.
     *
     * @param string $basePath Application root path
     */
    public static function init(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');

        $registryPath = self::$basePath . '/config/locales.php';
        if (is_file($registryPath)) {
            self::$registry = require $registryPath;
        }
    }

    /**
     * Set the active locale.
     */
    public static function setLocale(string $locale): void
    {
        if (isset(self::$registry[$locale])) {
            self::$locale = $locale;
        }
    }

    /**
     * Get the active locale code.
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Get the full registry entry for the active locale.
     *
     * @return array<string, mixed>
     */
    public static function getConfig(): array
    {
        return self::$registry[self::$locale] ?? self::$registry[self::$fallback] ?? [];
    }

    /**
     * Get the list of supported locale codes.
     *
     * @return string[]
     */
    public static function supported(): array
    {
        return array_keys(self::$registry);
    }

    /**
     * Check if a locale is supported.
     */
    public static function isSupported(string $locale): bool
    {
        return isset(self::$registry[$locale]);
    }

    // ════════════════════════════════════════════════════════════════
    // Translation
    // ════════════════════════════════════════════════════════════════

    /**
     * Translate a key with optional replacements.
     *
     * Key format: 'domain.section.element' or 'domain.key'
     * The domain maps to a file in lang/{locale}/{domain}.php.
     *
     * Replacements: ['name' => 'John'] replaces :name in the string.
     *
     * Falls back to the fallback locale, then returns the key itself.
     */
    public static function translate(string $key, array $replace = []): string
    {
        $parts = explode('.', $key, 2);
        if (count($parts) < 2) {
            return $key;
        }

        [$domain, $subKey] = $parts;

        // Try active locale
        $value = self::resolveFromFile($domain, $subKey, self::$locale);

        // Fallback to base locale
        if ($value === null && self::$locale !== self::$fallback) {
            $value = self::resolveFromFile($domain, $subKey, self::$fallback);
        }

        // Key not found — return the key itself
        if ($value === null) {
            return $key;
        }

        // Apply replacements
        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * Pluralize a translation key based on count.
     *
     * Supports ICU-style: '{0} None|{1} One|[2,*] :count items'
     */
    public static function plural(string $key, int $count, array $replace = []): string
    {
        $replace['count'] = (string) $count;
        $raw = self::translate($key, []);

        // If the raw value doesn't contain |, just do replacements
        if (!str_contains($raw, '|')) {
            return self::applyReplacements($raw, $replace);
        }

        $segments = explode('|', $raw);
        foreach ($segments as $segment) {
            $segment = trim($segment);

            // {exact} match: {0}, {1}
            if (preg_match('/^\{(\d+)\}\s*(.*)$/', $segment, $m)) {
                if ($count === (int) $m[1]) {
                    return self::applyReplacements($m[2], $replace);
                }
                continue;
            }

            // [min,max] range: [2,*]
            if (preg_match('/^\[(\d+),(\d+|\*)\]\s*(.*)$/', $segment, $m)) {
                $min = (int) $m[1];
                $max = $m[2] === '*' ? PHP_INT_MAX : (int) $m[2];
                if ($count >= $min && $count <= $max) {
                    return self::applyReplacements($m[3], $replace);
                }
                continue;
            }
        }

        // No match — use last segment as fallback (common: 'other' form)
        $last = trim(end($segments));
        // Strip any prefix like [2,*]
        $last = preg_replace('/^\{?\d+\}?\s*/', '', $last);
        $last = preg_replace('/^\[\d+,\d+\*?\]\s*/', '', $last);

        return self::applyReplacements($last, $replace);
    }

    // ════════════════════════════════════════════════════════════════
    // Number Formatting
    // ════════════════════════════════════════════════════════════════

    /**
     * Format a number according to the active locale.
     */
    public static function number(float $value, int $decimals = 0): string
    {
        $config = self::getConfig();

        return number_format(
            $value,
            $decimals,
            $config['decimal_sep'] ?? '.',
            $config['thousands_sep'] ?? ','
        );
    }

    /**
     * Format a currency value according to the active locale.
     */
    public static function currency(float $value, string $currencyCode): string
    {
        $config = self::getConfig();
        $symbol = self::currencySymbol($currencyCode);
        $formatted = self::number($value, 2);

        $space = ($config['currency_space'] ?? false) ? ' ' : '';

        if (($config['currency_position'] ?? 'before') === 'before') {
            return $symbol . $space . $formatted;
        }

        return $formatted . $space . $symbol;
    }

    // ════════════════════════════════════════════════════════════════
    // Date/Time Formatting
    // ════════════════════════════════════════════════════════════════

    /**
     * Format date (short): 03/27/2026 or 27-03-2026.
     */
    public static function date(\DateTimeInterface $dt): string
    {
        $config = self::getConfig();

        return $dt->format($config['date_format'] ?? 'Y-m-d');
    }

    /**
     * Format date (long): March 27, 2026 or 27 maart 2026.
     */
    public static function dateLong(\DateTimeInterface $dt): string
    {
        $config = self::getConfig();

        return $dt->format($config['date_format_long'] ?? 'F j, Y');
    }

    /**
     * Format time: 2:30 PM or 14:30.
     */
    public static function time(\DateTimeInterface $dt): string
    {
        $config = self::getConfig();

        return $dt->format($config['time_format'] ?? 'H:i');
    }

    /**
     * Format datetime.
     */
    public static function datetime(\DateTimeInterface $dt): string
    {
        $config = self::getConfig();

        return $dt->format($config['datetime_format'] ?? 'Y-m-d H:i');
    }

    /**
     * Format datetime with seconds (audit-grade precision).
     *
     * Reads datetime_full_format from locale config. Every locale in
     * config/locales.php defines this explicitly to handle 12h vs 24h
     * clocks correctly (e.g. 'g:i:s A' vs 'H:i:s').
     */
    public static function datetimeFull(\DateTimeInterface $dt): string
    {
        $config = self::getConfig();

        return $dt->format($config['datetime_full_format'] ?? 'Y-m-d H:i:s');
    }

    /**
     * Get day name for a day of the week (0=Sunday, 6=Saturday).
     */
    public static function dayName(int $dayOfWeek): string
    {
        $key = 'booking.days.' . $dayOfWeek;
        $value = self::translate($key);

        // Fallback if no translation
        if ($value === $key) {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            return $days[$dayOfWeek % 7];
        }

        return $value;
    }

    /**
     * Get month name (1-12).
     */
    public static function monthName(int $month): string
    {
        $key = 'booking.months.' . $month;
        $value = self::translate($key);

        // Fallback if no translation
        if ($value === $key) {
            $dt = \DateTimeImmutable::createFromFormat('!m', str_pad((string) $month, 2, '0', STR_PAD_LEFT));

            return $dt ? $dt->format('F') : '';
        }

        return $value;
    }

    /**
     * Get the week start day (0=Sunday, 1=Monday).
     */
    public static function weekStart(): int
    {
        $config = self::getConfig();

        return $config['week_start'] ?? 0;
    }

    // ════════════════════════════════════════════════════════════════
    // Locale Negotiation
    // ════════════════════════════════════════════════════════════════

    /**
     * Negotiate locale from browser Accept-Language header.
     *
     * Returns the best-matching supported locale, or the fallback.
     */
    public static function negotiateFromHeader(?string $acceptLanguage): string
    {
        if ($acceptLanguage === null || $acceptLanguage === '') {
            return self::$fallback;
        }

        // Parse Accept-Language: en-US,en;q=0.9,nl;q=0.8
        $candidates = [];
        foreach (explode(',', $acceptLanguage) as $part) {
            $parts = explode(';', trim($part));
            $tag = strtolower(trim($parts[0]));
            $q = 1.0;
            if (isset($parts[1]) && preg_match('/q=([0-9.]+)/', $parts[1], $m)) {
                $q = (float) $m[1];
            }
            $candidates[$tag] = $q;
        }

        arsort($candidates);

        foreach ($candidates as $tag => $q) {
            // Exact match: nl-NL -> nl
            if (self::isSupported($tag)) {
                return $tag;
            }
            // Language prefix: nl-NL -> nl
            $prefix = explode('-', $tag)[0];
            if (self::isSupported($prefix)) {
                return $prefix;
            }
        }

        return self::$fallback;
    }

    /**
     * Resolve and set locale for the public booking page.
     *
     * Resolution order:
     *   1. Tenant locale_override (explicit lock from operator settings)
     *   2. Browser Accept-Language (best supported match)
     *   3. Tenant default locale
     *   4. Fallback: 'en'
     *
     * @param array  $tenant          Tenant row (must contain 'locale', may contain 'locale_override')
     * @param string|null $acceptLang Accept-Language header value
     * @return string                 The resolved locale code
     */
    public static function resolveForBooking(array $tenant, ?string $acceptLang): string
    {
        // 1. Explicit operator lock
        $override = $tenant['locale_override'] ?? '';
        if ($override !== '' && self::isSupported($override)) {
            self::setLocale($override);
            return self::$locale;
        }

        // 2. Browser preference
        if ($acceptLang !== null && $acceptLang !== '') {
            $negotiated = self::negotiateFromHeader($acceptLang);
            if ($negotiated !== self::$fallback || str_starts_with(strtolower($acceptLang), 'en')) {
                self::setLocale($negotiated);
                return self::$locale;
            }
        }

        // 3. Tenant default
        $tenantLocale = $tenant['locale'] ?? '';
        if ($tenantLocale !== '' && self::isSupported($tenantLocale)) {
            self::setLocale($tenantLocale);
            return self::$locale;
        }

        // 4. Fallback
        self::setLocale(self::$fallback);
        return self::$locale;
    }

    // ════════════════════════════════════════════════════════════════
    // JS Payload
    // ════════════════════════════════════════════════════════════════

    /**
     * Get all translations for a domain, for injection into JS.
     *
     * Merges fallback (en) with the active locale so keys missing from
     * a partial translation file still resolve to English.
     *
     * @return array<string, string>
     */
    public static function getTranslationsForDomain(string $domain): array
    {
        // Always load English as the base
        self::loadFile($domain, self::$fallback);
        $fallbackKey = self::$fallback . '.' . $domain;
        $base = self::flattenArray(self::$translations[$fallbackKey] ?? []);

        // If active locale differs, overlay it on top of English
        if (self::$locale !== self::$fallback) {
            self::loadFile($domain, self::$locale);
            $cacheKey = self::$locale . '.' . $domain;
            $overlay = self::flattenArray(self::$translations[$cacheKey] ?? []);
            return array_merge($base, $overlay);
        }

        return $base;
    }

    /**
     * Get formatting config for JS (date/time/number formats, week start).
     *
     * @return array<string, mixed>
     */
    public static function getFormattingConfig(): array
    {
        $config = self::getConfig();

        return [
            'locale'           => self::$locale,
            'intl_locale'      => $config['intl_locale'] ?? 'en-US',
            'week_start'       => $config['week_start'] ?? 0,
            'time_format'      => $config['time_format'] ?? 'H:i',
            'date_format'      => $config['date_format'] ?? 'Y-m-d',
            'date_format_long' => $config['date_format_long'] ?? 'F j, Y',
            'decimal_sep'      => $config['decimal_sep'] ?? '.',
            'thousands_sep'    => $config['thousands_sep'] ?? ',',
            'currency_position'=> $config['currency_position'] ?? 'before',
            'currency_space'   => $config['currency_space'] ?? false,
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // Internal
    // ════════════════════════════════════════════════════════════════

    /**
     * Resolve a translation value from a domain file.
     */
    private static function resolveFromFile(string $domain, string $subKey, string $locale): ?string
    {
        self::loadFile($domain, $locale);

        $cacheKey = $locale . '.' . $domain;
        $data = self::$translations[$cacheKey] ?? [];

        // Support nested dot-notation: 'steps.service_title'
        $segments = explode('.', $subKey);
        $current = $data;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return is_string($current) ? $current : null;
    }

    /**
     * Load a translation file into the cache.
     */
    private static function loadFile(string $domain, string $locale): void
    {
        $cacheKey = $locale . '.' . $domain;
        if (isset(self::$translations[$cacheKey])) {
            return;
        }

        $path = self::$basePath . '/lang/' . $locale . '/' . $domain . '.php';

        if (is_file($path)) {
            $data = require $path;
            self::$translations[$cacheKey] = is_array($data) ? $data : [];
        } else {
            self::$translations[$cacheKey] = [];
        }
    }

    /**
     * Flatten a nested array into dot-notation keys.
     *
     * @return array<string, string>
     */
    private static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $key : (string) $key;
            if (is_array($value)) {
                $result = array_merge($result, self::flattenArray($value, $fullKey));
            } else {
                $result[$fullKey] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Apply :placeholder replacements to a string.
     */
    private static function applyReplacements(string $value, array $replace): string
    {
        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * Get the currency symbol for a given ISO 4217 code.
     */
    private static function currencySymbol(string $code): string
    {
        return match (strtoupper($code)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            'SEK', 'NOK', 'DKK' => 'kr',
            'PLN' => 'zł',
            'CZK' => 'Kč',
            default => $code,
        };
    }

    /**
     * Reset state (for testing).
     */
    public static function reset(): void
    {
        self::$locale = 'en';
        self::$registry = [];
        self::$translations = [];
        self::$basePath = '';
    }
}
