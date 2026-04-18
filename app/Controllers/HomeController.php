<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Engine\Database;
use App\Engine\FormState;
use App\Engine\Locale;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Ulid;
use App\Middleware\CsrfMiddleware;

/**
 * Public landing page and access request handler.
 *
 * GET  /                → homepage (simple logo/name default, or full landing
 *                         page with request form when enable_applications = 1)
 * POST /request-access  → processes the access request form submission
 *                         (only reachable when enable_applications = 1)
 */
final class HomeController
{
    public function index(Request $request): Response
    {
        $applicationsEnabled = $this->applicationsEnabled();

        // Only start a NEW session when the form is rendered (applications mode).
        // The simple homepage must not set a session cookie for anonymous visitors
        // to maintain the privacy posture that cookies are admin-only.
        //
        // However, if a vb_session cookie already exists (admin previously logged in),
        // resume that session so the nav shows "Dashboard" instead of "Log in".
        $csrfToken = '';
        $flash     = null;
        $isLoggedIn = false;
        $hasExistingSession = isset($_COOKIE['vb_session']);

        if ($applicationsEnabled || $hasExistingSession) {
            \App\Engine\Auth::startSession();
        }

        if ($applicationsEnabled) {
            $csrfToken = CsrfMiddleware::generateToken();
            $flash     = FormState::getToast();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $isLoggedIn = \App\Engine\Auth::check();
        }

        ob_start();
        require __DIR__ . '/../../templates/home.php';

        return Response::html(ob_get_clean());
    }

    public function submitRequest(Request $request): Response
    {
        if (!$this->applicationsEnabled()) {
            return Response::redirect('/');
        }

        // Anti-spam: timestamp check (field populated by JS on page load)
        $ts = $request->string('__ts');
        if ($ts === '' || !is_numeric($ts)) {
            FormState::toast('error', __('public.error_spam'));
            return Response::redirect('/');
        }
        $elapsed = time() * 1000 - (int) $ts;
        // Reject submissions under 3 seconds (bot speed) or over 1 hour (stale form)
        if ($elapsed < 3000 || $elapsed > 3_600_000) {
            FormState::toast('error', __('public.error_spam'));
            return Response::redirect('/');
        }

        // Anti-spam: honeypot (hidden field — bots fill it, humans don't)
        if (trim($request->string('__hp')) !== '') {
            FormState::toast('error', __('public.error_spam'));
            return Response::redirect('/');
        }

        $businessName = trim($request->string('business_name'));
        $contactName  = trim($request->string('contact_name'));
        $email        = trim($request->string('email'));
        $phone        = trim($request->string('phone')) ?: null;
        $website      = trim($request->string('website')) ?: null;
        $message      = trim($request->string('message')) ?: null;

        // Validation
        if ($businessName === '' || $contactName === '' || $email === '') {
            FormState::toast('error', __('public.error_required'));
            FormState::flashInput([
                'business_name' => $businessName,
                'contact_name'  => $contactName,
                'email'         => $email,
                'phone'         => $phone ?? '',
                'website'       => $website ?? '',
                'message'       => $message ?? '',
            ]);
            return Response::redirect('/');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            FormState::toast('error', __('public.error_email'));
            FormState::flashInput([
                'business_name' => $businessName,
                'contact_name'  => $contactName,
                'email'         => $email,
                'phone'         => $phone ?? '',
                'website'       => $website ?? '',
                'message'       => $message ?? '',
            ]);
            return Response::redirect('/');
        }

        // Duplicate check: pending request with same email
        $existing = Database::query(
            "SELECT `id` FROM `business_applications` WHERE `email` = ? AND `status` = 'pending' LIMIT 1",
            [$email]
        );

        if (!empty($existing)) {
            FormState::toast('error', __('public.error_duplicate'));
            FormState::flashInput([
                'business_name' => $businessName,
                'contact_name'  => $contactName,
                'email'         => $email,
                'phone'         => $phone ?? '',
                'website'       => $website ?? '',
                'message'       => $message ?? '',
            ]);
            return Response::redirect('/');
        }

        $id = Ulid::generate();

        Database::execute(
            'INSERT INTO `business_applications` (`id`, `business_name`, `contact_name`, `email`, `phone`, `website`, `message`, `status`, `created_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $businessName,
                $contactName,
                $email,
                $phone,
                $website,
                $message,
                'pending',
                date('Y-m-d H:i:s'),
            ]
        );

        FormState::toast('success', __('public.success_message'));
        return Response::redirect('/');
    }

    private function applicationsEnabled(): bool
    {
        try {
            $rows = Database::query(
                "SELECT `value` FROM `settings` WHERE `key` = 'enable_applications' LIMIT 1"
            );
            return ($rows[0]['value'] ?? '0') === '1';
        } catch (\Throwable) {
            return false;
        }
    }
}
