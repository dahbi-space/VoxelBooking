<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Engine\Request;
use App\Engine\Response;

/**
 * Health check controller for bootstrap verification.
 */
final class HealthController
{
    public function index(Request $request): Response
    {
        return Response::json([
            'status' => 'ok',
            'app'    => app_name(),
            'php'    => PHP_VERSION,
            'time'   => date('c'),
        ]);
    }
}
