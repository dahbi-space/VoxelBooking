<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\Mailer;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Ulid;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;

/**
 * Business user CRUD controller (tenant-scoped).
 *
 * Access: operators + business owners (Auth::canManageTenant()).
 * Managers cannot access any of these routes.
 *
 * GET  /admin/tenants/{tenant_id}/users               → list
 * GET  /admin/tenants/{tenant_id}/users/invite         → invite form
 * POST /admin/tenants/{tenant_id}/users/invite         → store
 * POST /admin/tenants/{tenant_id}/users/{id}/deactivate → deactivate
 * POST /admin/tenants/{tenant_id}/users/{id}/activate   → activate
 */
final class BusinessUsersController
{
    // ── List ──

    public function index(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $users = Database::query(
            'SELECT `id`, `name`, `email`, `role`, `is_active`, `last_login_at`, `created_at`
             FROM `business_users`
             WHERE `tenant_id` = ?
             ORDER BY `created_at` ASC',
            [$tenantId]
        );

        return $this->render('admin.tenants.users.index', __('admin.users.page_title'), [
            'documentTitle' => __('admin.users.page_title') . ' — ' . $tenant['name'],
            'tenant'   => $tenant,
            'users'    => $users,
            'flash'    => $this->flash(),
        ], $tenantId);
    }

    // ── Invite form ──

    public function invite(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        return $this->render('admin.tenants.users.invite', __('admin.users.invite_title'), [
            'documentTitle' => __('admin.users.invite_title') . ' — ' . $tenant['name'],
            'tenant' => $tenant,
            'flash'  => $this->flash(),
        ], $tenantId);
    }

    // ── Store invited user ──

    public function store(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $tenant = $this->loadTenant($tenantId);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $name     = trim($request->string('name'));
        $email    = trim($request->string('email'));
        $role     = trim($request->string('role'));
        $password = trim($request->string('password'));
        $sendEmail = $request->string('send_email') === '1';

        // Validate
        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.users.error_name_required');
        }
        if ($email === '') {
            $errors[] = __('admin.users.error_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('admin.users.error_email_invalid');
        }
        if (!in_array($role, ['owner', 'manager'], true)) {
            $errors[] = __('admin.users.error_role_invalid');
        }
        if ($password === '' || strlen($password) < 8) {
            $errors[] = __('admin.users.error_password_short');
        }

        // Check email uniqueness via auth_emails (PK enforces global uniqueness)
        if ($email !== '' && empty($errors)) {
            $existing = Database::query(
                'SELECT `email` FROM `auth_emails` WHERE `email` = ? LIMIT 1',
                [$email]
            );
            if (!empty($existing)) {
                $errors[] = __('admin.users.error_email_taken');
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            return Response::redirect("/admin/tenants/{$tenantId}/users/invite");
        }

        // Create user
        $userId = Ulid::generate();
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        Database::execute(
            "INSERT INTO `business_users`
             (`id`, `tenant_id`, `name`, `email`, `password_hash`, `role`, `is_active`, `force_password_change`)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1)",
            [$userId, $tenantId, $name, $email, $passwordHash, $role]
        );

        // Register in auth_emails for passwordless login
        Database::execute(
            "INSERT INTO `auth_emails` (`email`, `user_type`, `user_id`) VALUES (?, 'business_user', ?)",
            [$email, $userId]
        );

        AuditLog::log('business_user.created', 'business_user', $userId, [
            'tenant_id' => $tenantId,
            'role'      => $role,
            'email'     => AuditLog::hashEmail($email),
        ]);

        // Send welcome email
        if ($sendEmail && Mailer::isConfigured()) {
            $loginUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/admin/login';
            $emailResult = Mailer::sendBusinessUserWelcome(
                $email,
                $name,
                $password,
                $loginUrl,
                $tenant['name'],
                $tenantId
            );

            if ($emailResult['sent']) {
                $this->setFlash('success', __('admin.users.flash_invited') . ' ' . __('admin.users.flash_email_sent'));
                return Response::redirect("/admin/tenants/{$tenantId}/users");
            }

            // Email failed — show credentials
            $this->setFlash('warning', __('admin.users.flash_email_failed'));
            $_SESSION['owner_credentials'] = [
                'email'    => $email,
                'password' => $password,
            ];
            return Response::redirect("/admin/tenants/{$tenantId}/users");
        }

        // No email sent — show credentials
        $this->setFlash('success', __('admin.users.flash_invited'));
        $_SESSION['owner_credentials'] = [
            'email'    => $email,
            'password' => $password,
        ];
        return Response::redirect("/admin/tenants/{$tenantId}/users");
    }

    // ── Deactivate ──

    public function deactivate(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $userId   = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        // Verify user belongs to tenant
        $user = $this->loadUser($userId, $tenantId);
        if ($user === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/users");
        }

        // Cannot deactivate yourself
        $currentUser = Auth::user();
        if ($currentUser !== null && $currentUser['id'] === $userId) {
            $this->setFlash('error', __('admin.users.error_self_deactivate'));
            return Response::redirect("/admin/tenants/{$tenantId}/users");
        }

        Database::execute(
            'UPDATE `business_users` SET `is_active` = 0 WHERE `id` = ? AND `tenant_id` = ?',
            [$userId, $tenantId]
        );

        AuditLog::log('business_user.deactivated', 'business_user', $userId, [
            'tenant_id' => $tenantId,
            'email'     => AuditLog::hashEmail($user['email']),
        ]);

        $this->setFlash('success', __('admin.users.flash_deactivated'));
        return Response::redirect("/admin/tenants/{$tenantId}/users");
    }

    // ── Activate ──

    public function activate(Request $request): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $userId   = $request->getAttribute('id');

        if (!$this->canAccess($tenantId)) {
            return $this->forbidden($request);
        }

        $user = $this->loadUser($userId, $tenantId);
        if ($user === null) {
            return Response::redirect("/admin/tenants/{$tenantId}/users");
        }

        Database::execute(
            'UPDATE `business_users` SET `is_active` = 1 WHERE `id` = ? AND `tenant_id` = ?',
            [$userId, $tenantId]
        );

        AuditLog::log('business_user.activated', 'business_user', $userId, [
            'tenant_id' => $tenantId,
            'email'     => AuditLog::hashEmail($user['email']),
        ]);

        $this->setFlash('success', __('admin.users.flash_activated'));
        return Response::redirect("/admin/tenants/{$tenantId}/users");
    }

    // ── Helpers ──

    private function canAccess(string $tenantId): bool
    {
        if (!Auth::canAccessTenant($tenantId)) {
            return false;
        }

        return Auth::canManageTenant();
    }

    private function loadTenant(string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT `id`, `name`, `slug`, `email` FROM `tenants` WHERE `id` = ? LIMIT 1',
            [$tenantId]
        );

        return $rows[0] ?? null;
    }

    private function loadUser(string $userId, string $tenantId): ?array
    {
        $rows = Database::query(
            'SELECT `id`, `name`, `email`, `role`, `is_active`
             FROM `business_users`
             WHERE `id` = ? AND `tenant_id` = ?
             LIMIT 1',
            [$userId, $tenantId]
        );

        return $rows[0] ?? null;
    }

    private function render(string $template, string $pageTitle, array $extra, string $tenantId): Response
    {
        return View::response($template, array_merge([
            'user'       => Auth::user(),
            'version'    => Version::get(),
            'pageTitle'  => $pageTitle,
            'activePage' => 'users',
            'csrfToken'  => CsrfMiddleware::generateToken(),
            'tenantId'   => $tenantId,
        ], $extra));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error'   => 'forbidden',
                'message' => 'Owner or operator access required.',
            ], 403);
        }

        return View::response('admin.errors.403', [
            'user'      => Auth::user(),
            'version'   => Version::get(),
            'pageTitle' => '403',
            'csrfToken' => CsrfMiddleware::generateToken(),
        ], 403);
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

        // Check for credentials to show
        $credentials = $_SESSION['owner_credentials'] ?? null;
        unset($_SESSION['owner_credentials']);

        if ($credentials !== null) {
            return [
                'type'        => $flash['type'] ?? 'success',
                'message'     => $flash['message'] ?? '',
                'credentials' => $credentials,
            ];
        }

        return $flash;
    }
}
