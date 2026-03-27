<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Template rendering engine using PHP includes.
 *
 * Renders PHP template files with data. Supports layouts.
 * Templates live in the templates/ directory.
 */
final class View
{
    private static string $basePath = '';

    public static function init(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    /**
     * Render a template with data and return the HTML string.
     *
     * @param string $template Dot-notation path (e.g., 'admin.dashboard')
     * @param array<string, mixed> $data Variables available in the template
     */
    public static function render(string $template, array $data = []): string
    {
        $path = self::resolvePath($template);

        if (!is_file($path)) {
            throw new \RuntimeException("Template not found: {$template} ({$path})");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $path;

        return ob_get_clean() ?: '';
    }

    /**
     * Render a template and return it as an HTML Response.
     */
    public static function response(string $template, array $data = [], int $status = 200): Response
    {
        $html = self::render($template, $data);

        return Response::html($html, $status);
    }

    /**
     * Render a template partial within another template.
     */
    public static function partial(string $template, array $data = []): string
    {
        return self::render($template, $data);
    }

    /**
     * Escape HTML output.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function resolvePath(string $template): string
    {
        $relativePath = str_replace('.', '/', $template) . '.php';

        return self::$basePath . '/' . $relativePath;
    }
}
