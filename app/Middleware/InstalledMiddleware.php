<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;

/**
 * Checks if VoxelBooking is installed. Redirects to /install if not.
 *
 * Per PRD §IX Installation Wizard:
 * - Checks for `installed_at` in the settings table
 * - If not installed and not already on /install, redirect to /install
 * - If installed and on /install, redirect to /admin
 */
final class InstalledMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $path = $request->path();
        $isInstallRoute = str_starts_with($path, '/install');
        $isHealthRoute = $path === '/health';
        $installed = $this->isInstalled();

        // Health endpoint always passes through
        if ($isHealthRoute) {
            return $next($request);
        }

        // Not installed → redirect to /install (unless already there)
        if (!$installed && !$isInstallRoute) {
            return Response::redirect('/install');
        }

        // Installed → block /install routes
        if ($installed && $isInstallRoute) {
            return Response::redirect('/admin');
        }

        return $next($request);
    }

    private function isInstalled(): bool
    {
        try {
            if (!Database::canConnect()) {
                return false;
            }

            if (!Database::tableExists('settings')) {
                return false;
            }

            $result = Database::query(
                "SELECT `value` FROM `settings` WHERE `key` = 'installed_at'"
            );

            return !empty($result[0]['value'] ?? '');
        } catch (\Throwable) {
            return false;
        }
    }
}
