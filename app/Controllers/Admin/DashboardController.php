<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;

/**
 * Operator dashboard controller.
 */
final class DashboardController
{
    public function index(Request $request): Response
    {
        return View::response('admin.dashboard', [
            'user'    => Auth::user(),
            'version' => Version::get(),
        ]);
    }
}
