<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Engine\Auth;
use App\Engine\Database;
use App\Engine\FormState;
use App\Engine\LoginToken;
use App\Engine\Mailer;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Handles operator and business user authentication.
 *
 * GET  /admin/login              → Login form (password + OTP + magic link tabs)
 * POST /admin/login              → Password authenticate
 * POST /admin/login/request-code → Issue OTP or magic link
 * GET  /admin/login/verify-code  → OTP entry form
 * POST /admin/login/verify-code  → Validate OTP
 * GET  /admin/login/verify       → Handle magic link click
 * POST /auth/logout              → Log out
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

        $toast = FormState::getToast();
        $error = ($toast && $toast['type'] === 'error') ? $toast['message'] : null;
        $success = ($toast && $toast['type'] === 'success') ? $toast['message'] : null;

        return View::response('auth.login', [
            'csrfToken'  => $csrfToken,
            'error'      => $error,
            'success'    => $success,
        ]);
    }

    /**
     * Process login form submission (password method).
     */
    public function login(Request $request): Response
    {
        $email = $request->string('email');
        $password = $request->string('password');

        $result = Auth::login($email, $password);

        if ($result['success']) {
            if ($request->string('remember_me') === '1') {
                $_SESSION['remember_me'] = true;
            } else {
                unset($_SESSION['remember_me']);
            }
            return $this->redirectAfterLogin();
        }

        // Store error and submitted email in session and redirect back to login
        Auth::startSession();
        FormState::toast('error', $result['error'] ?? __('auth.invalid_credentials'));
        FormState::flashInput(['email' => $email]);

        return Response::redirect('/admin/login');
    }

    /**
     * Issue an OTP code or magic link.
     *
     * POST /admin/login/request-code
     * Params: email, method (otp|magic_link), remember_me (0|1)
     */
    public function requestCode(Request $request): Response
    {
        $email      = trim($request->string('email'));
        $method     = $request->string('method');
        $rememberMe = $request->string('remember_me') === '1';
        $ip         = $request->ip();

        Auth::startSession();

        // Gate: mail must be configured for passwordless login
        if (!Mailer::isConfigured()) {
            FormState::toast('error', __('auth.passwordless_unavailable'));
            return Response::redirect('/admin/login');
        }

        // Check if email exists in auth_emails
        // Timing-safe: unknown emails still get a generic success for magic links,
        // but OTP redirects to verify-code which would fail on verify anyway.
        $reg = Database::query(
            'SELECT `email` FROM `auth_emails` WHERE `email` = ? LIMIT 1',
            [$email]
        );

        $issued = false;
        $sendOk = false;

        if (!empty($reg)) {
            if ($method === 'magic_link') {
                $result = LoginToken::createMagicLink($email, $ip, $rememberMe);
                if ($result['success']) {
                    $link = rtrim($_ENV['APP_URL'] ?? '', '/') . '/admin/login/verify?token=' . $result['token'];
                    $mailResult = Mailer::sendMagicLink($email, $link);
                    $issued = true;
                    $sendOk = $mailResult['sent'] ?? false;
                }
            } else {
                $result = LoginToken::createOtp($email, $ip);
                if ($result['success']) {
                    $mailResult = Mailer::sendLoginCode($email, $result['code']);
                    $issued = true;
                    $sendOk = $mailResult['sent'] ?? false;
                }
            }
        }

        // Magic link: always show "check your email" (timing-safe for unknown emails)
        if ($method === 'magic_link') {
            if ($issued && !$sendOk) {
                FormState::toast('error', __('auth.send_failed'));
            } else {
                FormState::toast('success', __('auth.check_email'));
            }
            return Response::redirect('/admin/login');
        }

        // OTP: only redirect to verify-code if we actually issued and sent
        if ($issued && $sendOk) {
            FormState::toast('success', __('auth.code_sent'));
            return Response::redirect('/admin/login/verify-code?email=' . urlencode($email));
        }

        // OTP failed: show error on the login page, not the verify page
        if ($issued && !$sendOk) {
            FormState::toast('error', __('auth.send_failed'));
        } else {
            FormState::toast('success', __('auth.code_sent'));
        }
        return Response::redirect('/admin/login');
    }

    /**
     * Show the OTP entry form.
     *
     * GET /admin/login/verify-code?email=...
     */
    public function showVerifyCode(Request $request): Response
    {
        Auth::startSession();

        if (Auth::check()) {
            return $this->redirectAfterLogin();
        }

        $email = $request->query('email', '');

        $toast = FormState::getToast();
        $error = ($toast && $toast['type'] === 'error') ? $toast['message'] : null;
        $success = ($toast && $toast['type'] === 'success') ? $toast['message'] : null;

        return View::response('auth.verify-code', [
            'csrfToken' => CsrfMiddleware::generateToken(),
            'email'     => $email,
            'error'     => $error,
            'success'   => $success,
        ]);
    }

    /**
     * Validate an OTP code.
     *
     * POST /admin/login/verify-code
     * Params: email, code, remember_me (0|1)
     */
    public function verifyCode(Request $request): Response
    {
        $email = trim($request->string('email'));
        $code  = trim($request->string('code'));

        $result = LoginToken::verifyOtp($email, $code);

        if (!$result['success']) {
            Auth::startSession();
            FormState::toast('error', __('auth.code_invalid'));
            return Response::redirect('/admin/login/verify-code?email=' . urlencode($email));
        }

        // OTP verified — log in by email
        $loginResult = Auth::loginByEmail($result['email']);

        if (!$loginResult['success']) {
            Auth::startSession();
            FormState::toast('error', $loginResult['error'] ?? __('auth.invalid_credentials'));
            return Response::redirect('/admin/login');
        }

        // Remember-me from the verify-code form (not the token row)
        if ($request->string('remember_me') === '1') {
            $_SESSION['remember_me'] = true;
        } else {
            unset($_SESSION['remember_me']);
        }

        return $this->redirectAfterLogin();
    }

    /**
     * Handle a magic-link click.
     *
     * GET /admin/login/verify?token=...
     */
    public function verifyMagicLink(Request $request): Response
    {
        Auth::startSession();

        $tokenRaw = $request->query('token', '');

        if ($tokenRaw === '') {
            FormState::toast('error', __('auth.link_invalid'));
            return Response::redirect('/admin/login');
        }

        $result = LoginToken::verifyMagicLink($tokenRaw);

        if (!$result['success']) {
            FormState::toast('error', __('auth.link_invalid'));
            return Response::redirect('/admin/login');
        }

        // Magic link verified — log in by email
        $loginResult = Auth::loginByEmail($result['email']);

        if (!$loginResult['success']) {
            FormState::toast('error', $loginResult['error'] ?? __('auth.invalid_credentials'));
            return Response::redirect('/admin/login');
        }

        // Remember-me from the token row (stored at issuance)
        if ($result['remember_me']) {
            $_SESSION['remember_me'] = true;
        } else {
            unset($_SESSION['remember_me']);
        }

        return $this->redirectAfterLogin();
    }

    /**
     * Log out — or exit impersonation.
     *
     * If impersonating: exits impersonation, keeps operator session, redirects to /admin/tenants.
     * Otherwise: real logout, redirects to /admin/login.
     */
    public function logout(Request $request): Response
    {
        $wasImpersonating = Auth::logout();

        if ($wasImpersonating) {
            return Response::redirect('/admin/tenants');
        }

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
