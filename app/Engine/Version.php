<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Reads the product version from the root VERSION file.
 *
 * This is the single canonical source of truth for the application version.
 * Do not hardcode version strings elsewhere. Use Version::get() instead.
 *
 * The VERSION file lives at the project root and contains a single semver
 * string (e.g. "1.0.0") with an optional trailing newline.
 */
final class Version
{
    private static ?string $cached = null;

    /**
     * Get the current product version.
     *
     * Reads the root VERSION file once and caches the result for the
     * remainder of the request. Returns '0.0.0' if the file is missing
     * or unreadable (defensive — should never happen in production).
     */
    public static function get(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $path = self::filePath();

        if (!is_file($path) || !is_readable($path)) {
            return self::$cached = '0.0.0';
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return self::$cached = '0.0.0';
        }

        self::$cached = trim($contents);

        return self::$cached;
    }

    /**
     * Absolute path to the VERSION file.
     */
    public static function filePath(): string
    {
        return dirname(__DIR__, 2) . '/VERSION';
    }

    /**
     * Reset the cached version (for testing).
     */
    public static function reset(): void
    {
        self::$cached = null;
    }
}
