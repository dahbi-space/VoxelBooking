<?php

declare(strict_types=1);

namespace App\Perka\Shared;

use App\Engine\Response;

/**
 * Minimal module-view renderer for Perka.
 *
 * Mirrors the core App\Engine\View::render mechanism (extract + output
 * buffering + include) but resolves templates from inside a module rather than
 * the core `templates/` directory — which core View cannot reach. This lets
 * Perka views live entirely within their module (app/Perka/Modules/<Module>/
 * Views/) with no core change.
 *
 * Usage:
 *   PerkaView::render('PublicProfile', 'public/profile', ['tenant' => ...]);
 *   PerkaView::response('PublicProfile', 'public/404', [], 404);
 *
 * Paths: app/Perka/Modules/{Module}/Views/{view}.php  (dots in $view → slashes)
 */
final class PerkaView
{
    /**
     * Render a module view file to an HTML string.
     *
     * @param string $module Module directory name, e.g. "PublicProfile"
     * @param string $view   View path under the module's Views/, e.g. "public/profile"
     * @param array<string, mixed> $data Variables extracted into the view scope
     */
    public static function render(string $module, string $view, array $data = []): string
    {
        $path = self::modulesPath()
            . '/' . $module
            . '/Views/' . str_replace('.', '/', $view) . '.php';

        return self::renderPath($path, $data);
    }

    /**
     * Render an arbitrary view file (module OR platform) to an HTML string.
     *
     * Used for platform-level views (e.g. app/Perka/Platform/Views) that do not
     * live under a module's Views/ directory.
     *
     * @param string $path Absolute path to the .php view file
     * @param array<string, mixed> $data Variables extracted into the view scope
     */
    public static function renderPath(string $path, array $data = []): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Perka view not found: {$path}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $path;

        return ob_get_clean() ?: '';
    }

    /**
     * Render a module view and wrap it in an HTML Response.
     */
    public static function response(string $module, string $view, array $data = [], int $status = 200): Response
    {
        return Response::html(self::render($module, $view, $data), $status);
    }

    /**
     * Escape a value for safe HTML output (same contract as core View::e).
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Absolute path to app/Perka/Modules (this file lives in app/Perka/Shared).
     */
    private static function modulesPath(): string
    {
        return dirname(__DIR__) . '/Modules';
    }
}
