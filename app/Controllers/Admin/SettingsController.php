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
 * System settings controller (operator-only).
 *
 * GET  /admin/settings          → General settings (read + form)
 * POST /admin/settings          → Save general settings
 * GET  /admin/settings/account  → Account password form
 * POST /admin/settings/account  → Change operator password
 * GET  /admin/settings/email    → Email/SMTP config
 * POST /admin/settings/email    → Save email config
 * GET  /admin/settings/cron     → Cron status
 * GET  /admin/settings/logs     → Log viewer
 * GET  /admin/settings/audit    → Audit log viewer (read-only, compliance §5)
 */
final class SettingsController
{
    // ── General ──

    public function general(Request $request): Response
    {
        $settings = $this->loadSettings(['app_name', 'app_url', 'timezone', 'date_format']);

        return $this->render('admin.settings.general', 'General', [
            'settings' => $settings,
            'flash'    => $this->flash(),
        ]);
    }

    public function saveGeneral(Request $request): Response
    {
        $appName    = trim($request->string('app_name'));
        $appUrl     = rtrim(trim($request->string('app_url')), '/');
        $timezone   = trim($request->string('timezone'));
        $dateFormat = trim($request->string('date_format'));

        $errors = [];
        if ($appName === '') {
            $errors[] = 'Application name is required.';
        }
        if ($appUrl !== '' && !filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Application URL must be a valid URL.';
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(' ', $errors));
            return Response::redirect('/admin/settings');
        }

        // Audit log: track what changed
        $oldSettings = $this->loadSettings(['app_name', 'app_url', 'timezone', 'date_format']);
        $changes = [];

        $this->saveSetting('app_name', $appName);
        if ($appName !== ($oldSettings['app_name'] ?? '')) {
            $changes['app_name'] = ['old' => $oldSettings['app_name'] ?? '', 'new' => $appName];
        }
        if ($appUrl !== '') {
            $this->saveSetting('app_url', $appUrl);
            if ($appUrl !== ($oldSettings['app_url'] ?? '')) {
                $changes['app_url'] = ['old' => $oldSettings['app_url'] ?? '', 'new' => $appUrl];
            }
        }
        if ($timezone !== '') {
            $this->saveSetting('timezone', $timezone);
            if ($timezone !== ($oldSettings['timezone'] ?? '')) {
                $changes['timezone'] = ['old' => $oldSettings['timezone'] ?? '', 'new' => $timezone];
            }
        }
        if ($dateFormat !== '') {
            $this->saveSetting('date_format', $dateFormat);
            if ($dateFormat !== ($oldSettings['date_format'] ?? '')) {
                $changes['date_format'] = ['old' => $oldSettings['date_format'] ?? '', 'new' => $dateFormat];
            }
        }

        if (!empty($changes)) {
            AuditLog::logSettingsChanged($changes);
        }

        $this->setFlash('success', 'General settings saved.');
        return Response::redirect('/admin/settings');
    }

    // ── Account ──

    public function account(Request $request): Response
    {
        return $this->render('admin.settings.account', 'Account', [
            'flash' => $this->flash(),
        ]);
    }

    public function saveAccount(Request $request): Response
    {
        $currentPassword = $request->string('current_password');
        $newPassword     = $request->string('new_password');
        $confirmPassword = $request->string('confirm_password');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $this->setFlash('error', 'All password fields are required.');
            return Response::redirect('/admin/settings/account');
        }

        if ($newPassword !== $confirmPassword) {
            $this->setFlash('error', 'New passwords do not match.');
            return Response::redirect('/admin/settings/account');
        }

        if (strlen($newPassword) < 8) {
            $this->setFlash('error', 'New password must be at least 8 characters.');
            return Response::redirect('/admin/settings/account');
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
                $this->setFlash('error', 'Current password is incorrect.');
                return Response::redirect('/admin/settings/account');
            }

            // Update password (bcrypt, cost 12 per PRD §XV)
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::execute(
                "UPDATE `{$table}` SET `password_hash` = ?, `updated_at` = NOW() WHERE `id` = ?",
                [$newHash, $user['id']]
            );

            AuditLog::log('auth.password_changed', $user['type'], $user['id']);

            $this->setFlash('success', 'Password updated successfully.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to update password. Please try again.');
        }

        return Response::redirect('/admin/settings/account');
    }

    // ── Email ──

    public function email(Request $request): Response
    {
        $settings = $this->loadSettings([
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption',
            'mail_from_address', 'mail_from_name',
        ]);

        return $this->render('admin.settings.email', 'Email', [
            'settings' => $settings,
            'flash'    => $this->flash(),
        ]);
    }

    public function saveEmail(Request $request): Response
    {
        // Track changes for audit log
        $oldSettings = $this->loadSettings(['smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption', 'mail_from_address', 'mail_from_name']);
        $changes = [];

        $fields = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption', 'mail_from_address', 'mail_from_name'];
        foreach ($fields as $field) {
            $value = trim($request->string($field));
            if ($value !== '') {
                $this->saveSetting($field, $value);
                if ($value !== ($oldSettings[$field] ?? '')) {
                    $changes[$field] = ['old' => $oldSettings[$field] ?? '', 'new' => $value];
                }
            }
        }

        // Save password separately (don't overwrite if blank)
        $smtpPassword = $request->string('smtp_password');
        if ($smtpPassword !== '') {
            $this->saveSetting('smtp_password', $smtpPassword);
            $changes['smtp_password'] = ['old' => '[REDACTED]', 'new' => '[REDACTED]'];
        }

        if (!empty($changes)) {
            AuditLog::logSettingsChanged($changes);
        }

        $this->setFlash('success', 'Email settings saved.');
        return Response::redirect('/admin/settings/email');
    }

    // ── Cron ──

    public function cron(Request $request): Response
    {
        $cronToken = $this->getSetting('cron_token');
        if ($cronToken === '') {
            $cronToken = bin2hex(random_bytes(32));
            $this->saveSetting('cron_token', $cronToken);
        }

        $lastRun = $this->getSetting('cron_last_run');

        return $this->render('admin.settings.cron', 'Cron', [
            'cronToken' => $cronToken,
            'lastRun'   => $lastRun,
            'flash'     => $this->flash(),
        ]);
    }

    // ── Logs ──

    public function logs(Request $request): Response
    {
        $logContent = '';
        $logFile = dirname(__DIR__, 3) . '/storage/logs/app.log';

        if (is_file($logFile)) {
            // Read last 100 lines
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $logContent = implode("\n", array_slice($lines, -100));
            }
        }

        return $this->render('admin.settings.logs', 'Logs', [
            'logContent' => $logContent,
        ]);
    }

    // ── Audit Log ──

    public function audit(Request $request): Response
    {
        $page = max(1, $request->int('page', 1));
        $perPage = 50;
        $actionFilter = $request->query('action');

        $result = AuditLog::query(
            limit: $perPage,
            offset: ($page - 1) * $perPage,
            action: $actionFilter !== '' ? $actionFilter : null,
        );

        return $this->render('admin.settings.audit', 'Audit Log', [
            'entries'      => $result['entries'],
            'total'        => $result['total'],
            'page'         => $page,
            'perPage'      => $perPage,
            'actionFilter' => $actionFilter,
        ]);
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

    /**
     * Load multiple settings from the settings table.
     *
     * @return array<string, string>
     */
    private function loadSettings(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getSetting($key);
        }
        return $result;
    }

    private function getSetting(string $key): string
    {
        try {
            $rows = Database::query(
                'SELECT `value` FROM `settings` WHERE `key` = ? LIMIT 1',
                [$key]
            );
            return $rows[0]['value'] ?? '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function saveSetting(string $key, string $value): void
    {
        try {
            Database::execute(
                'INSERT INTO `settings` (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                [$key, $value]
            );
        } catch (\Throwable) {
            // Log would go here
        }
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
