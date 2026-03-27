<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Brand color calculator for tenant booking pages.
 *
 * Derives dynamic brand tokens from a single hex brand_color:
 * - brand-hover: 8% darker for interactive states
 * - brand-light: 95% lightness tint for selected backgrounds
 * - brand-text: white or dark text per WCAG luminance contrast
 * - brand-rgb: comma-separated RGB for use in rgba()
 *
 * Per .ai/11 §3 (Booking Page Aesthetics): these are the ONLY per-tenant
 * CSS variations. The compiled booking.css is identical for all tenants.
 */
final class BrandColorHelper
{
    /**
     * Calculate all derived brand tokens from a hex color.
     *
     * @return array{brand: string, brand_hover: string, brand_light: string, brand_text: string, brand_rgb: string}
     */
    public static function derive(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            $hex = '2563EB'; // Default blue
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        [$h, $s, $l] = self::rgbToHsl($r, $g, $b);

        // brand-hover: 8% darker
        $hoverL = max(0, $l - 0.08);
        [$hr, $hg, $hb] = self::hslToRgb($h, $s, $hoverL);
        $brandHover = sprintf('#%02X%02X%02X', $hr, $hg, $hb);

        // brand-light: lightness set to 95%
        [$lr, $lg, $lb] = self::hslToRgb($h, $s, 0.95);
        $brandLight = sprintf('#%02X%02X%02X', $lr, $lg, $lb);

        // brand-text: WCAG relative luminance > 0.5 → dark text, otherwise white
        $brandText = self::relativeLuminance($r, $g, $b) > 0.5 ? '#111827' : '#FFFFFF';

        // brand-rgb: for use in rgba(var(--vb-brand-rgb), 0.15)
        $brandRgb = "{$r}, {$g}, {$b}";

        return [
            'brand'       => '#' . strtoupper($hex),
            'brand_hover' => $brandHover,
            'brand_light' => $brandLight,
            'brand_text'  => $brandText,
            'brand_rgb'   => $brandRgb,
        ];
    }

    /**
     * Generate the inline <style> block for brand injection.
     */
    public static function inlineStyle(string $hex): string
    {
        $tokens = self::derive($hex);

        return sprintf(
            ':root { --vb-brand: %s; --vb-brand-hover: %s; --vb-brand-light: %s; --vb-brand-text: %s; --vb-brand-rgb: %s; }',
            $tokens['brand'],
            $tokens['brand_hover'],
            $tokens['brand_light'],
            $tokens['brand_text'],
            $tokens['brand_rgb'],
        );
    }

    /**
     * RGB to HSL conversion.
     * @return array{0: float, 1: float, 2: float} [H 0-360, S 0-1, L 0-1]
     */
    private static function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match (true) {
            $max === $r => (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6,
            $max === $g => (($b - $r) / $d + 2) / 6,
            default     => (($r - $g) / $d + 4) / 6,
        };

        return [$h * 360, $s, $l];
    }

    /**
     * HSL to RGB conversion.
     * @return array{0: int, 1: int, 2: int} [R 0-255, G 0-255, B 0-255]
     */
    private static function hslToRgb(float $h, float $s, float $l): array
    {
        $h /= 360;

        if ($s === 0.0) {
            $v = (int) round($l * 255);
            return [$v, $v, $v];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return [
            (int) round(self::hueToRgb($p, $q, $h + 1 / 3) * 255),
            (int) round(self::hueToRgb($p, $q, $h) * 255),
            (int) round(self::hueToRgb($p, $q, $h - 1 / 3) * 255),
        ];
    }

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    }

    /**
     * WCAG 2.1 relative luminance.
     */
    private static function relativeLuminance(int $r, int $g, int $b): float
    {
        $srgb = function (int $c): float {
            $c /= 255;
            return $c <= 0.04045
                ? $c / 12.92
                : pow(($c + 0.055) / 1.055, 2.4);
        };

        return 0.2126 * $srgb($r) + 0.7152 * $srgb($g) + 0.0722 * $srgb($b);
    }
}
