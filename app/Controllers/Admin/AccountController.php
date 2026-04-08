<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\FormState;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Authenticated account controller — shared personal surface.
 *
 * Accessible to both operators and business users. Sits outside
 * the OPERATOR_ONLY_PREFIXES in AuthMiddleware, so any authenticated
 * user can manage their own credentials and profile here.
 *
 * GET  /admin/account        → Profile details (editable) + password form
 * POST /admin/account        → Save profile changes
 * POST /admin/account/password → Save password change
 */
final class AccountController
{
    public function show(Request $request): Response
    {
        return $this->render('admin.account', __('admin.account.page_title'), [
            'flash' => FormState::getToast(),
        ]);
    }

    /**
     * Save profile changes (name, email).
     */
    public function save(Request $request): Response
    {
        $action = $request->string('_action');

        if ($action === 'password') {
            return $this->savePassword($request);
        }

        return $this->saveProfile($request);
    }

    // ── Profile ──

    private function saveProfile(Request $request): Response
    {
        $name  = trim($request->string('name'));
        $email = trim(strtolower($request->string('email')));

        if ($name === '') {
            FormState::toast('error', __('admin.flash.profile_name_required'));
            return Response::redirect('/admin/account');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            FormState::toast('error', __('admin.flash.profile_email_invalid'));
            return Response::redirect('/admin/account');
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/admin/login');
        }

        $table      = $user['type'] === 'operator' ? 'operators' : 'business_users';
        $oldEmail   = $user['email'] ?? '';
        $oldName    = $user['name'] ?? '';
        $emailChanged = ($email !== strtolower($oldEmail));

        try {
            // If email is changing, check for uniqueness
            if ($emailChanged) {
                $exists = Database::query(
                    "SELECT 1 FROM `auth_emails` WHERE `email` = ? AND `user_id` != ? LIMIT 1",
                    [$email, $user['id']]
                );
                if (!empty($exists)) {
                    FormState::toast('error', __('admin.flash.profile_email_taken'));
                    return Response::redirect('/admin/account');
                }
            }

            // Update profile
            Database::execute(
                "UPDATE `{$table}` SET `name` = ?, `email` = ?, `updated_at` = NOW() WHERE `id` = ?",
                [$name, $email, $user['id']]
            );

            // Update auth_emails registry if email changed
            if ($emailChanged) {
                Database::execute(
                    "UPDATE `auth_emails` SET `email` = ? WHERE `user_type` = ? AND `user_id` = ?",
                    [$email, $user['type'], $user['id']]
                );
            }

            // Refresh session user data so the header reflects the change immediately
            $_SESSION['auth_name']  = $name;
            $_SESSION['auth_email'] = $email;

            // Audit log
            $changes = [];
            if ($name !== $oldName)  $changes['name']  = ['old' => $oldName, 'new' => $name];
            if ($emailChanged)       $changes['email'] = ['old' => $oldEmail, 'new' => $email];

            if (!empty($changes)) {
                AuditLog::log('account.profile_updated', $user['type'], $user['id'], $changes);
            }

            FormState::toast('success', __('admin.flash.profile_updated'));
        } catch (\Throwable $e) {
            FormState::toast('error', __('admin.flash.profile_failed'));
        }

        return Response::redirect('/admin/account');
    }

    // ── Password ──

    private function savePassword(Request $request): Response
    {
        $currentPassword = $request->string('current_password');
        $newPassword     = $request->string('new_password');
        $confirmPassword = $request->string('confirm_password');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            FormState::toast('error', __('admin.flash.password_required'));
            return Response::redirect('/admin/account');
        }

        if ($newPassword !== $confirmPassword) {
            FormState::toast('error', __('admin.flash.password_mismatch'));
            return Response::redirect('/admin/account');
        }

        if (strlen($newPassword) < 8) {
            FormState::toast('error', __('admin.flash.password_min_length'));
            return Response::redirect('/admin/account');
        }

        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/admin/login');
        }

        try {
            $table = $user['type'] === 'operator' ? 'operators' : 'business_users';
            $rows = Database::query(
                "SELECT `password_hash` FROM `{$table}` WHERE `id` = ? LIMIT 1",
                [$user['id']]
            );

            if (empty($rows) || !password_verify($currentPassword, $rows[0]['password_hash'])) {
                FormState::toast('error', __('admin.flash.password_incorrect'));
                return Response::redirect('/admin/account');
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::execute(
                "UPDATE `{$table}` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?",
                [$newHash, $user['id']]
            );

            AuditLog::log('auth.password_changed', $user['type'], $user['id']);

            FormState::toast('success', __('admin.flash.password_updated'));
        } catch (\Throwable $e) {
            FormState::toast('error', __('admin.flash.password_failed'));
        }

        return Response::redirect('/admin/account');
    }

    // ── Helpers ──

    private function render(string $template, string $pageTitle, array $extra = []): Response
    {
        return View::response($template, array_merge([
            'user'      => Auth::user(),
            'version'   => Version::get(),
            'pageTitle' => $pageTitle,
            'csrfToken' => CsrfMiddleware::generateToken(),
        ], $extra));
    }


}
