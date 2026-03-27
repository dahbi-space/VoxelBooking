<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Engine\Request;
use App\Engine\Response;

/**
 * Root route handler.
 */
final class HomeController
{
    public function index(Request $request): Response
    {
        return Response::redirect('/admin');
    }
}
