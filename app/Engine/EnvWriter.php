<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Writes key-value pairs to .env files.
 *
 * Updates existing keys in place. Appends new keys.
 * Preserves comments and blank lines. Quotes values containing spaces.
 * Creates the file if it doesn't exist.
 */
final class EnvWriter
{
    /**
     * Set a single key-value pair.
     */
    public static function set(string $path, string $key, string $value): void
    {
        self::setMultiple($path, [$key => $value]);
    }

    /**
     * Set multiple key-value pairs.
     */
    public static function setMultiple(string $path, array $pairs): void
    {
        // Read existing lines (or start empty)
        $lines = [];
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        }

        $updated = [];

        // Update existing keys
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            // Skip comments and blank lines — keep them as-is
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!str_contains($trimmed, '=')) {
                continue;
            }

            [$existingKey] = explode('=', $trimmed, 2);
            $existingKey = trim($existingKey);

            if (array_key_exists($existingKey, $pairs)) {
                $lines[$i] = $existingKey . '=' . self::formatValue($pairs[$existingKey]);
                $updated[$existingKey] = true;
            }
        }

        // Append new keys
        foreach ($pairs as $key => $value) {
            if (!isset($updated[$key])) {
                $lines[] = $key . '=' . self::formatValue($value);
            }
        }

        // Write back
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX);
    }

    /**
     * Format a value for .env output. Quote if it contains spaces.
     */
    private static function formatValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (str_contains($value, ' ') || str_contains($value, '#')) {
            return '"' . $value . '"';
        }

        return $value;
    }
}
