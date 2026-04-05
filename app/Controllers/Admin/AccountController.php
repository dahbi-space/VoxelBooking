<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Operator account controller — standalone personal surface.
 *
 * GET  /admin/account  → Account details + password change form
 * POST /admin/account  → Save password change
 *
 * Separated from SettingsController so operator identity management
 * lives outside the system-settings namespace. Available to both
 * operators and business users so all authenticated users can manage
 * their own credentials.
 */
final class AccountController
{
    public function show(Request $request): Response
    {
        return $this->render('admin.settings.account', __('admin.account.page_title'), [
            'flash' => $this->flash(),
        ]);
    }

    public function save(Request $request): Response
    {
        $currentPassword = $request->string('current_password');
        $newPassword     = $request->string('new_password');
        $confirmPassword = $request->string('confirm_password');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $this->setFlash('error', __('admin.flash.password_required'));
            return Response::redirect('/admin/account');
        }

        if ($newPassword !== $confirmPassword) {
            $this->setFlash('error', __('admin.flash.password_mismatch'));
            return Response::redirect('/admin/account');
        }

        if (strlen($newPassword) < 8) {
            $this->setFlash('error', __('admin.flash.password_min_length'));
            return Response::redirect('/admin/account');
        }

        // Verify current password
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
                $this->setFlash('error', __('admin.flash.password_incorrect'));
                return Response::redirect('/admin/account');
            }

            // Update password (bcrypt, cost 12 per PRD §XV)
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::execute(
                "UPDATE `{$table}` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?",
                [$newHash, $user['id']]
            );

            AuditLog::log('auth.password_changed', $user['type'], $user['id']);

            $this->setFlash('success', __('admin.flash.password_updated'));
        } catch (\Throwable $e) {
            $this->setFlash('error', __('admin.flash.password_failed'));
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

    private function setFlash(string $type, string $message): void
    {
        Auth::startSession();
        $_SESSION['settings_flash'] = ['type' => $type, 'message' => $message];
    }

    private function flash(): ?array
    {
        $flash = $_SESSION['settings_flash'] ?? null;
        unset($_SESSION['settings_flash']);
        return $flash;
    }
}
