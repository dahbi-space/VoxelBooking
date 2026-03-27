<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * System settings controller (operator-only).
 *
 * Skeletal pages for Phase 2. Routes and layout are real;
 * form persistence comes in later phases.
 */
final class SettingsController
{
    public function general(Request $request): Response
    {
        return $this->render('admin.settings.general', 'General');
    }

    public function account(Request $request): Response
    {
        return $this->render('admin.settings.account', 'Account');
    }

    public function email(Request $request): Response
    {
        return $this->render('admin.settings.email', 'Email');
    }

    public function cron(Request $request): Response
    {
        return $this->render('admin.settings.cron', 'Cron');
    }

    public function logs(Request $request): Response
    {
        return $this->render('admin.settings.logs', 'Logs');
    }

    private function render(string $template, string $pageTitle): Response
    {
        return View::response($template, [
            'user'      => Auth::user(),
            'version'   => Version::get(),
            'pageTitle' => $pageTitle,
            'csrfToken' => CsrfMiddleware::generateToken(),
        ]);
    }
}
