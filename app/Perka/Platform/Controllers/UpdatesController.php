<?php

declare(strict_types=1);

namespace App\Perka\Platform\Controllers;

use App\Engine\AuditLog;
use App\Engine\Auth;
use App\Engine\Database;
use App\Engine\FormState;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Perka\Shared\PerkaMigrator;
use App\Perka\Shared\PerkaView;

/**
 * Perka platform Updates page — runs migrations for ALL modules.
 *
 * Lives at platform level (app/Perka/Platform), NOT inside any module, because
 * it serves every module and must keep working if any single module (e.g.
 * PublicProfile) is deleted.
 *
 * Routes (registered in app/Perka/Platform/routes.php inside a Perka
 * AuthMiddleware group):
 *   GET  /admin/perka/updates      index()
 *   POST /admin/perka/updates/run  run()
 *
 * Operator-only, enforced HERE via Auth::isOperator() — mirroring core's
 * "/admin/updates" convention. AuthMiddleware::OPERATOR_ONLY_PREFIXES is a core
 * const and is intentionally NOT modified.
 */
final class UpdatesController
{
    public function index(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $migrator = $this->migrator();
        // hasPending() ensures the registry table exists before we read it.
        $pending  = $migrator->pendingCount();
        $applied  = Database::query(
            'SELECT `migration`, `applied_at` FROM `perka_migrations` ORDER BY `applied_at` ASC, `migration` ASC'
        );

        $body = PerkaView::renderPath($this->viewPath('updates'), [
            'pendingCount' => $pending,
            'applied'      => $applied,
            'csrfToken'    => CsrfMiddleware::generateToken(),
        ]);

        return $this->shell($body, 'Perka Updates');
    }

    public function run(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        try {
            $result = $this->migrator()->run();
        } catch (\Throwable $e) {
            FormState::toast('error', 'Migration failed: ' . $e->getMessage());
            return Response::redirect('/admin/perka/updates');
        }

        if ($result === PerkaMigrator::SKIPPED_LOCKED) {
            FormState::toast('info', 'Another migration run is already in progress.');
        } elseif ($result === 0) {
            FormState::toast('info', 'No pending migrations.');
        } else {
            AuditLog::log('perka.migrations_run', 'system', null, ['applied' => $result]);
            FormState::toast('success', "Applied {$result} migration(s).");
        }

        return Response::redirect('/admin/perka/updates');
    }

    // ── Helpers ──

    private function migrator(): PerkaMigrator
    {
        // Platform/Controllers → app/Perka ; + /Modules
        return new PerkaMigrator(dirname(__DIR__, 2) . '/Modules');
    }

    private function viewPath(string $view): string
    {
        // Platform/Controllers → app/Perka/Platform ; + /Views/<view>.php
        return dirname(__DIR__) . '/Views/' . $view . '.php';
    }

    private function shell(string $content, string $title): Response
    {
        return View::response('admin.layout', [
            'user'          => Auth::user(),
            'version'       => Version::get(),
            'pageTitle'     => $title,
            'documentTitle' => $title,
            'activePage'    => 'perka-updates',
            'csrfToken'     => CsrfMiddleware::generateToken(),
            'content'       => $content,
            'flash'         => FormState::getToast(),
        ]);
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Operator access required.',
            ], 403);
        }

        try {
            return View::response('admin.errors.403', [
                'user'      => Auth::user(),
                'version'   => Version::get(),
                'pageTitle' => '403',
                'csrfToken' => CsrfMiddleware::generateToken(),
            ], 403);
        } catch (\Throwable) {
            return Response::html('<h1>403 Forbidden</h1><p>Access denied.</p>', 403);
        }
    }
}
