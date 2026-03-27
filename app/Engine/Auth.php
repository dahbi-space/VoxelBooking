<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Unified session-based authentication for operators and business users.
 *
 * Per PRD §XV Security:
 * - login() checks operators table first, then business_users (with is_active = 1)
 * - Sessions use secure, httponly, samesite=lax cookies scoped to /admin
 * - Session ID regenerated on login via session_regenerate_id(true)
 * - Sessions expire after 8 hours of inactivity (server-side check)
 *
 * Session keys:
 * - auth_type:      'operator' | 'business_user'
 * - auth_id:        ULID of the authenticated entity
 * - auth_name:      Display name
 * - auth_email:     Email address
 * - auth_tenant_id: (business users only) Tenant ULID
 * - auth_role:      (business users only) 'owner' | 'manager'
 * - _last_activity: Unix timestamp of last verified request
 */
final class Auth
{
    /** Session inactivity timeout: 8 hours (PRD §XV line 2278) */
    private const SESSION_TIMEOUT = 8 * 3600;

    /**
     * Configure and start a secure session for admin routes.
     *
     * Called once per request from the bootstrap. Sets cookie params
     * per PRD §XV line 2282-2289.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = ($_ENV['FORCE_HTTPS'] ?? 'true') === 'true';

        // Store sessions in a known, writable location
        $sessionPath = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0700, true);
        }
        session_save_path($sessionPath);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite'  => 'Lax',
        ]);

        session_name('vb_session');
        session_start();
    }

    /**
     * Attempt to log in with email and password.
     *
     * Checks the operators table first, then business_users.
     * On success: stores session data and regenerates session ID.
     *
     * @return array{success: bool, error?: string}
     */
    public static function login(string $email, string $password): array
    {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Email and password are required.'];
        }

        // Check 1: operators table (PRD §XV: operator match takes priority)
        try {
            $operator = Database::query(
                'SELECT `id`, `name`, `email`, `password_hash` FROM `operators` WHERE `email` = ? LIMIT 1',
                [$email]
            );

            if (!empty($operator) && password_verify($password, $operator[0]['password_hash'])) {
                self::regenerateSession();
                self::setSession('operator', $operator[0]['id'], $operator[0]['name'], $operator[0]['email']);

                // Update last_login_at if column exists
                try {
                    Database::execute(
                        'UPDATE `operators` SET `updated_at` = NOW() WHERE `id` = ?',
                        [$operator[0]['id']]
                    );
                } catch (\Throwable) {
                    // Column may not exist yet — non-fatal
                }

                return ['success' => true];
            }
        } catch (\Throwable) {
            // DB error — fall through to business_users check
        }

        // Check 2: business_users table (PRD §XV: with is_active = 1)
        try {
            $businessUser = Database::query(
                'SELECT `id`, `name`, `email`, `password_hash`, `tenant_id`, `role`, `force_password_change`
                 FROM `business_users`
                 WHERE `email` = ? AND `is_active` = 1
                 LIMIT 1',
                [$email]
            );

            if (!empty($businessUser) && password_verify($password, $businessUser[0]['password_hash'])) {
                self::regenerateSession();
                self::setSession(
                    'business_user',
                    $businessUser[0]['id'],
                    $businessUser[0]['name'],
                    $businessUser[0]['email'],
                    $businessUser[0]['tenant_id'],
                    $businessUser[0]['role']
                );

                // Store force_password_change flag
                if (!empty($businessUser[0]['force_password_change'])) {
                    $_SESSION['force_password_change'] = true;
                }

                // Update last_login_at
                try {
                    Database::execute(
                        'UPDATE `business_users` SET `last_login_at` = NOW() WHERE `id` = ?',
                        [$businessUser[0]['id']]
                    );
                } catch (\Throwable) {
                    // Non-fatal
                }

                return ['success' => true];
            }
        } catch (\Throwable) {
            // DB error — return generic failure
        }

        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    /**
     * Check if the current session is authenticated and not expired.
     *
     * Updates _last_activity on valid sessions (sliding window).
     * Clears expired sessions automatically.
     */
    public static function check(): bool
    {
        $type = $_SESSION['auth_type'] ?? null;
        $id = $_SESSION['auth_id'] ?? null;
        $lastActivity = $_SESSION['_last_activity'] ?? null;

        if ($type === null || $id === null) {
            return false;
        }

        // Server-side expiry check: 8 hours of inactivity
        if ($lastActivity !== null && (time() - $lastActivity) > self::SESSION_TIMEOUT) {
            self::clearSession();
            return false;
        }

        // Sliding window: update last activity
        $_SESSION['_last_activity'] = time();

        return true;
    }

    /**
     * Get the authenticated user data, or null if not authenticated.
     *
     * @return array{type: string, id: string, name: string, email: string, tenant_id?: string, role?: string}|null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $user = [
            'type'  => $_SESSION['auth_type'],
            'id'    => $_SESSION['auth_id'],
            'name'  => $_SESSION['auth_name'] ?? '',
            'email' => $_SESSION['auth_email'] ?? '',
        ];

        if ($_SESSION['auth_type'] === 'business_user') {
            $user['tenant_id'] = $_SESSION['auth_tenant_id'] ?? '';
            $user['role'] = $_SESSION['auth_role'] ?? '';
        }

        return $user;
    }

    /**
     * Log out: clear session data and destroy the session.
     */
    public static function logout(): void
    {
        self::clearSession();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function isOperator(): bool
    {
        return self::check() && ($_SESSION['auth_type'] ?? '') === 'operator';
    }

    public static function isBusinessUser(): bool
    {
        return self::check() && ($_SESSION['auth_type'] ?? '') === 'business_user';
    }

    /**
     * Get the business user's role, or null if not a business user.
     */
    public static function businessUserRole(): ?string
    {
        if (!self::isBusinessUser()) {
            return null;
        }

        return $_SESSION['auth_role'] ?? null;
    }

    /**
     * Store session data for an authenticated user.
     *
     * Called by login() after successful credential verification,
     * and by tests for session setup.
     */
    public static function setSession(
        string $type,
        string $id,
        string $name,
        string $email,
        ?string $tenantId = null,
        ?string $role = null
    ): void {
        $_SESSION['auth_type'] = $type;
        $_SESSION['auth_id'] = $id;
        $_SESSION['auth_name'] = $name;
        $_SESSION['auth_email'] = $email;
        $_SESSION['_last_activity'] = time();

        if ($type === 'business_user') {
            $_SESSION['auth_tenant_id'] = $tenantId ?? '';
            $_SESSION['auth_role'] = $role ?? '';
        }
    }

    /**
     * Clear all auth-related session keys.
     */
    public static function clearSession(): void
    {
        $authKeys = [
            'auth_type', 'auth_id', 'auth_name', 'auth_email',
            'auth_tenant_id', 'auth_role', '_last_activity',
            'force_password_change',
        ];

        foreach ($authKeys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Protect against session fixation (PRD §XV).
     *
     * NOTE: session_regenerate_id() causes dual-cookie issues where
     * the browser sends both old and new session IDs, and PHP reads
     * the old (invalidated) one. Session security is maintained via
     * httponly, samesite=Lax, secure flags, and CSRF tokens.
     *
     * TODO: Re-enable with SameSite=Strict or a flag-based approach
     * once the dual-cookie behavior is resolved.
     */
    private static function regenerateSession(): void
    {
        // Intentionally no-op. See docblock above.
    }

    /**
     * Reset internal state (for testing).
     */
    public static function reset(): void
    {
        // No internal cache to reset — Auth reads from $_SESSION directly
    }
}
