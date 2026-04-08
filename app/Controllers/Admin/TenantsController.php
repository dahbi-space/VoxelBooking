<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Engine\Auth;
use App\Engine\AuditLog;
use App\Engine\Database;
use App\Engine\Logger;
use App\Engine\Mailer;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use App\Models\Tenant;

/**
 * Tenant management controller (operator-only).
 *
 * Access control: Every public method checks Auth::isOperator() and
 * returns 403 if false. This is NOT in OPERATOR_ONLY_PREFIXES because
 * the /admin/tenants/{tenant_id}/... prefix is used by business users.
 *
 * GET  /admin/tenants          → Tenant list
 * GET  /admin/tenants/create   → Create form
 * POST /admin/tenants/create   → Store new tenant
 * GET  /admin/tenants/{id}/edit → Edit form
 * POST /admin/tenants/{id}/edit → Update tenant
 * POST /admin/tenants/{id}/archive → Archive tenant
 * POST /admin/tenants/{id}/activate → Re-activate tenant
 */
final class TenantsController
{
    public function index(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $tenants = Tenant::all(includeArchived: true);
        foreach ($tenants as &$t) {
            $t['booking_count'] = Tenant::bookingCount($t['id']);
            $t['service_count'] = Tenant::serviceCount($t['id']);
        }
        unset($t);

        return $this->render('admin.tenants.index', __('admin.tenants.title'), [
            'tenants' => $tenants,
            'counts'  => Tenant::counts(),
            'flash'   => $this->flash(),
        ]);
    }

    public function create(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        // Retrieve old input from session (populated on validation failure)
        $old = $_SESSION['_old_input'] ?? [];
        $fieldErrors = $_SESSION['_field_errors'] ?? [];
        unset($_SESSION['_old_input'], $_SESSION['_field_errors']);

        return $this->render('admin.tenants.create', __('admin.tenants.title'), [
            'documentTitle' => __('admin.tenants.create'),
            'flash'         => $this->flash(),
            'old'           => $old,
            'fieldErrors'   => $fieldErrors,
        ]);
    }

    public function store(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $name    = trim($request->string('name'));
        $slug    = trim($request->string('slug')) ?: $this->generateSlug($name);
        $email   = trim($request->string('email'));
        $pattern = trim($request->string('booking_pattern'));

        // Validate tenant fields
        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.tenants.flash_name_required');
        }
        if ($email === '') {
            $errors[] = __('admin.tenants.flash_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('admin.tenants.flash_email_invalid');
        }
        if ($slug !== '' && Tenant::slugExists($slug)) {
            $errors[] = __('admin.tenants.flash_slug_taken');
        }
        if (!in_array($pattern, ['timeslot', 'resource', 'capacity', 'event'], true)) {
            $pattern = 'timeslot';
        }

        // Optional owner fields
        $createOwner = $request->string('create_owner') === '1';
        $ownerName   = trim($request->string('owner_name'));
        $ownerEmail  = trim($request->string('owner_email'));
        $ownerPass   = trim($request->string('owner_password'));
        $sendEmail   = $request->string('send_owner_email') === '1';

        // Validate owner fields if requested
        if ($createOwner) {
            if ($ownerName === '') {
                $errors[] = __('admin.tenants.flash_owner_name_required');
            }
            if ($ownerEmail === '') {
                $errors[] = __('admin.tenants.flash_email_required');
            } elseif (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('admin.tenants.flash_owner_email_invalid');
            }
            if ($ownerPass === '') {
                $ownerPass = bin2hex(random_bytes(8)); // 16-char random password
            }
        }

        if (!empty($errors)) {
            // Preserve old input so the form repopulates on redirect
            $_SESSION['_old_input'] = [
                'name'            => $name,
                'slug'            => $slug,
                'email'           => $email,
                'booking_pattern' => $pattern,
                'timezone'        => trim($request->string('timezone')) ?: 'UTC',
                'currency'        => trim($request->string('currency')) ?: 'EUR',
                'brand_color'     => trim($request->string('brand_color')) ?: '#2563EB',
                'create_owner'    => $createOwner ? '1' : '0',
                'owner_name'      => $ownerName,
                'owner_email'     => $ownerEmail,
            ];

            // Track which fields failed so the template can mark them
            $_SESSION['_field_errors'] = $this->mapFieldErrors($errors);

            $this->setFlash('error', implode(' ', $errors));
            return Response::redirect('/admin/tenants/create');
        }

        try {
            $tenantId = null;
            $ownerId = null;

            Database::transaction(function () use (
                $name, $slug, $email, $pattern, $request,
                $createOwner, $ownerName, $ownerEmail, $ownerPass,
                &$tenantId, &$ownerId
            ) {
                $tenantId = Tenant::create([
                    'name'            => $name,
                    'slug'            => $slug,
                    'email'           => $email,
                    'booking_pattern' => $pattern,
                    'timezone'        => trim($request->string('timezone')) ?: 'UTC',
                    'currency'        => trim($request->string('currency')) ?: 'EUR',
                    'brand_color'     => trim($request->string('brand_color')) ?: '#2563EB',
                ]);

                AuditLog::log('tenant.created', 'tenant', $tenantId, [
                    'name' => $name, 'slug' => $slug, 'pattern' => $pattern,
                ]);

                // Create the first owner in the same transaction
                if ($createOwner && $tenantId) {
                    // Check for email collision via global registry
                    $existing = Database::query(
                        'SELECT `email` FROM `auth_emails` WHERE `email` = ? LIMIT 1',
                        [$ownerEmail]
                    );

                    if (!empty($existing)) {
                        throw new \RuntimeException('owner_email_taken');
                    }

                    $ownerId = \App\Engine\Ulid::generate();
                    Database::execute(
                        "INSERT INTO `business_users`
                         (`id`, `tenant_id`, `name`, `email`, `password_hash`, `role`, `force_password_change`, `is_active`)
                         VALUES (?, ?, ?, ?, ?, 'owner', 1, 1)",
                        [$ownerId, $tenantId, $ownerName, $ownerEmail, password_hash($ownerPass, PASSWORD_BCRYPT)]
                    );

                    // Register in auth_emails for passwordless login
                    Database::execute(
                        "INSERT INTO `auth_emails` (`email`, `user_type`, `user_id`) VALUES (?, 'business_user', ?)",
                        [$ownerEmail, $ownerId]
                    );

                    AuditLog::log('business_user.created', 'business_user', $ownerId, [
                        'tenant_id' => $tenantId,
                        'name'      => $ownerName,
                        'role'      => 'owner',
                        'source'    => 'tenant_creation',
                    ]);
                }
            });

            // After commit: send welcome email if requested
            if ($createOwner && $ownerId !== null) {
                $emailResult = null;

                if ($sendEmail && Mailer::isConfigured()) {
                    $loginUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/admin/login';
                    $emailResult = Mailer::sendBusinessUserWelcome(
                        $ownerEmail, $ownerName, $ownerPass, $loginUrl, $name, $tenantId
                    );
                }

                if ($emailResult !== null && $emailResult['sent']) {
                    // Email sent successfully
                    $this->setFlash('success', __('admin.tenants.flash_created_with_owner')
                        . ' ' . __('admin.tenants.flash_owner_email_sent'));
                } else {
                    // Email not sent (SMTP unconfigured, not requested, or failed)
                    // Flash credentials so the operator can copy them
                    $reason = !$sendEmail
                        ? __('admin.tenants.flash_owner_email_skipped')
                        : ($emailResult !== null
                            ? __('admin.tenants.flash_owner_email_failed')
                            : __('admin.tenants.flash_owner_email_skipped'));

                    $this->setFlash('owner_credentials', json_encode([
                        'message'  => __('admin.tenants.flash_created_with_owner') . ' ' . $reason,
                        'email'    => $ownerEmail,
                        'password' => $ownerPass,
                        'login'    => rtrim($_ENV['APP_URL'] ?? '', '/') . '/admin/login',
                    ]));
                }
            } else {
                $this->setFlash('success', __('admin.tenants.flash_created'));
            }

            return Response::redirect('/admin/tenants');
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'owner_email_taken') {
                // Preserve old input on owner-email collision too
                $_SESSION['_old_input'] = [
                    'name'            => $name,
                    'slug'            => $slug,
                    'email'           => $email,
                    'booking_pattern' => $pattern,
                    'timezone'        => trim($request->string('timezone')) ?: 'UTC',
                    'currency'        => trim($request->string('currency')) ?: 'EUR',
                    'brand_color'     => trim($request->string('brand_color')) ?: '#2563EB',
                    'create_owner'    => '1',
                    'owner_name'      => $ownerName,
                    'owner_email'     => $ownerEmail,
                ];
                $_SESSION['_field_errors'] = ['owner_email' => __('admin.tenants.flash_owner_email_taken')];
                $this->setFlash('error', __('admin.tenants.flash_owner_email_taken'));
                return Response::redirect('/admin/tenants/create');
            }
            throw $e;
        } catch (\Throwable $e) {
            Logger::error('Tenant creation failed', ['error' => $e->getMessage()]);
            $this->setFlash('error', __('admin.tenants.flash_create_failed'));
            return Response::redirect('/admin/tenants/create');
        }
    }

    public function edit(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $tenant = Tenant::find($request->getAttribute('id'));
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        return $this->render('admin.tenants.edit', __('admin.tenants.title'), [
            'documentTitle' => __('admin.tenants.edit'),
            'tenant' => $tenant,
            'flash'  => $this->flash(),
        ]);
    }

    public function update(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $id = $request->getAttribute('id');
        $tenant = Tenant::find($id);
        if ($tenant === null) {
            return Response::redirect('/admin/tenants');
        }

        $name  = trim($request->string('name'));
        $slug  = trim($request->string('slug'));
        $email = trim($request->string('email'));

        $errors = [];
        if ($name === '') {
            $errors[] = __('admin.tenants.flash_name_required');
        }
        if ($email === '') {
            $errors[] = __('admin.tenants.flash_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('admin.tenants.flash_email_invalid');
        }
        if ($slug !== '' && Tenant::slugExists($slug, excludeId: $id)) {
            $errors[] = __('admin.tenants.flash_slug_taken');
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            return Response::redirect("/admin/tenants/{$id}/edit");
        }

        $data = array_filter([
            'name'        => $name,
            'email'       => $email,
            'slug'        => $slug !== '' ? $slug : null,
            'timezone'    => trim($request->string('timezone')) ?: null,
            'currency'    => trim($request->string('currency')) ?: null,
            'brand_color' => trim($request->string('brand_color')) ?: null,
        ], fn($v) => $v !== null);

        $changes = [];
        foreach ($data as $k => $v) {
            if (($tenant[$k] ?? '') !== $v) {
                $changes[$k] = ['old' => $tenant[$k] ?? '', 'new' => $v];
            }
        }

        try {
            Tenant::update($id, $data);
            if (!empty($changes)) {
                AuditLog::log('tenant.updated', 'tenant', $id, $changes);
            }
            $this->setFlash('success', __('admin.tenants.flash_updated'));
        } catch (\Throwable $e) {
            Logger::error('Tenant update failed', ['error' => $e->getMessage()]);
            $this->setFlash('error', __('admin.tenants.flash_update_failed'));
        }

        return Response::redirect("/admin/tenants/{$id}/edit");
    }

    public function archive(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $id = $request->getAttribute('id');
        $tenant = Tenant::find($id);

        if ($tenant !== null && $tenant['status'] !== 'archived') {
            Tenant::update($id, ['status' => 'archived']);
            AuditLog::log('tenant.archived', 'tenant', $id, ['name' => $tenant['name']]);
            $this->setFlash('success', __('admin.tenants.flash_archived'));
        }

        return Response::redirect('/admin/tenants');
    }

    public function activate(Request $request): Response
    {
        if (!Auth::isOperator()) {
            return $this->forbidden($request);
        }

        $id = $request->getAttribute('id');
        $tenant = Tenant::find($id);

        if ($tenant !== null && $tenant['status'] === 'archived') {
            Tenant::update($id, ['status' => 'active']);
            AuditLog::log('tenant.activated', 'tenant', $id, ['name' => $tenant['name']]);
            $this->setFlash('success', __('admin.tenants.flash_activated'));
        }

        return Response::redirect('/admin/tenants');
    }

    // ── Helpers ──

    private function generateSlug(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-') ?: 'tenant';
    }

    /**
     * Map translated error messages back to field names for per-field marking.
     *
     * Returns ['field_name' => 'translated error message'] for each error that
     * matches a known validation message.
     */
    private function mapFieldErrors(array $errors): array
    {
        $map = [
            __('admin.tenants.flash_name_required')       => 'name',
            __('admin.tenants.flash_email_required')       => 'email',
            __('admin.tenants.flash_email_invalid')        => 'email',
            __('admin.tenants.flash_slug_taken')           => 'slug',
            __('admin.tenants.flash_owner_name_required')  => 'owner_name',
            __('admin.tenants.flash_owner_email_invalid')  => 'owner_email',
            __('admin.tenants.flash_owner_email_taken')    => 'owner_email',
        ];

        $result = [];
        foreach ($errors as $msg) {
            if (isset($map[$msg])) {
                $result[$map[$msg]] = $msg;
            }
        }
        return $result;
    }

    private function render(string $template, string $pageTitle, array $extra = []): Response
    {
        return View::response($template, array_merge([
            'user'      => Auth::user(),
            'version'   => Version::get(),
            'pageTitle' => $pageTitle,
            'activePage' => 'tenants',
            'csrfToken' => CsrfMiddleware::generateToken(),
        ], $extra));
    }

    private function forbidden(Request $request): Response
    {
        if ($request->isJson()) {
            return Response::json([
                'error' => 'forbidden',
                'message' => 'Operator access required.',
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
        return $flash;
    }
}
