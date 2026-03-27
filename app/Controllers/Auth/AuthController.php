<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Engine\Auth;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Handles operator and business user authentication.
 *
 * GET  /admin/login  → Login form
 * POST /admin/login  → Authenticate
 * POST /auth/logout  → Log out
 */
final class AuthController
{
    /**
     * Show the login page.
     */
    public function showLogin(Request $request): Response
    {
        // Start session before checking auth state (GET requests don't
        // pass through CsrfMiddleware's session startup path)
        Auth::startSession();

        // Already authenticated → redirect to admin
        if (Auth::check()) {
            return $this->redirectAfterLogin();
        }

        $csrfToken = CsrfMiddleware::generateToken();

        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        $lastEmail = $_SESSION['login_email'] ?? '';
        unset($_SESSION['login_email']);

        return View::response('auth.login', [
            'csrfToken'  => $csrfToken,
            'error'      => $error,
            'lastEmail'  => $lastEmail,
        ]);
    }

    /**
     * Process login form submission.
     */
    public function login(Request $request): Response
    {
        $email = $request->string('email');
        $password = $request->string('password');

        $result = Auth::login($email, $password);

        if ($result['success']) {
            return $this->redirectAfterLogin();
        }

        // Store error and submitted email in session and redirect back to login
        Auth::startSession();
        $_SESSION['login_error'] = $result['error'] ?? 'Invalid email or password.';
        $_SESSION['login_email'] = $email;

        return Response::redirect('/admin/login');
    }

    /**
     * Log out and redirect to login.
     */
    public function logout(Request $request): Response
    {
        Auth::logout();

        return Response::redirect('/admin/login');
    }

    /**
     * Redirect to the appropriate dashboard after login.
     *
     * Operators → /admin
     * Business users → /admin/tenants/{tenant-id}
     */
    private function redirectAfterLogin(): Response
    {
        if (Auth::isBusinessUser()) {
            $user = Auth::user();
            $tenantId = $user['tenant_id'] ?? '';
            if ($tenantId !== '') {
                return Response::redirect("/admin/tenants/{$tenantId}");
            }
        }

        return Response::redirect('/admin');
    }
}
