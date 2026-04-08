<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Token engine for passwordless login (OTP codes and magic links).
 *
 * OTP: 6-digit codes, SHA-256 hashed, 10-minute expiry, max 3 active per email.
 * Magic link: 256-bit random tokens, SHA-256 hashed, 15-minute expiry, new issuance invalidates previous.
 * Both: 5 total issuances per email per 15 minutes (flood protection).
 */
final class LoginToken
{
    private const OTP_EXPIRY_MINUTES = 10;
    private const MAGIC_LINK_EXPIRY_MINUTES = 15;
    private const MAX_ACTIVE_OTP = 3;
    private const FLOOD_LIMIT = 5;
    private const FLOOD_WINDOW_MINUTES = 15;

    /**
     * Create an OTP code for the given email.
     *
     * @return array{success: bool, code?: string, error?: string}
     */
    public static function createOtp(string $email, string $ip): array
    {
        // Flood protection: max 5 tokens per email per 15 min
        $floodCheck = self::checkFloodLimit($email);
        if ($floodCheck !== null) {
            return $floodCheck;
        }

        // Active OTP limit: max 3 unexpired/unused
        $active = Database::query(
            "SELECT COUNT(*) as cnt FROM `login_tokens`
             WHERE `email` = ? AND `type` = 'otp' AND `expires_at` > NOW() AND `used_at` IS NULL",
            [$email]
        );
        if (((int) ($active[0]['cnt'] ?? 0)) >= self::MAX_ACTIVE_OTP) {
            return ['success' => false, 'error' => 'too_many_active'];
        }

        $code = (string) random_int(100000, 999999);
        $hash = hash('sha256', $code);
        $id = Ulid::generate();

        Database::execute(
            "INSERT INTO `login_tokens` (`id`, `email`, `type`, `token_hash`, `remember_me`, `expires_at`, `ip_address`)
             VALUES (?, ?, 'otp', ?, 0, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)",
            [$id, $email, $hash, self::OTP_EXPIRY_MINUTES, $ip]
        );

        return ['success' => true, 'code' => $code];
    }

    /**
     * Create a magic-link token for the given email.
     *
     * Invalidates all previous unused magic links for the same email.
     *
     * @return array{success: bool, token?: string, error?: string}
     */
    public static function createMagicLink(string $email, string $ip, bool $rememberMe = false): array
    {
        // Flood protection: max 5 tokens per email per 15 min
        $floodCheck = self::checkFloodLimit($email);
        if ($floodCheck !== null) {
            return $floodCheck;
        }

        // Invalidate all previous unused magic links for this email
        Database::execute(
            "UPDATE `login_tokens` SET `used_at` = NOW()
             WHERE `email` = ? AND `type` = 'magic_link' AND `used_at` IS NULL",
            [$email]
        );

        $rawToken = bin2hex(random_bytes(32)); // 64-char hex
        $hash = hash('sha256', $rawToken);
        $id = Ulid::generate();

        Database::execute(
            "INSERT INTO `login_tokens` (`id`, `email`, `type`, `token_hash`, `remember_me`, `expires_at`, `ip_address`)
             VALUES (?, ?, 'magic_link', ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)",
            [$id, $email, $hash, $rememberMe ? 1 : 0, self::MAGIC_LINK_EXPIRY_MINUTES, $ip]
        );

        return ['success' => true, 'token' => $rawToken];
    }

    /**
     * Verify an OTP code.
     *
     * @return array{success: bool, email?: string, error?: string}
     */
    public static function verifyOtp(string $email, string $code): array
    {
        $hash = hash('sha256', $code);

        $rows = Database::query(
            "SELECT `id`, `email` FROM `login_tokens`
             WHERE `email` = ? AND `type` = 'otp' AND `token_hash` = ?
               AND `expires_at` > NOW() AND `used_at` IS NULL
             LIMIT 1",
            [$email, $hash]
        );

        if (empty($rows)) {
            return ['success' => false, 'error' => 'invalid_code'];
        }

        // Mark as used (single-use)
        Database::execute(
            "UPDATE `login_tokens` SET `used_at` = NOW() WHERE `id` = ?",
            [$rows[0]['id']]
        );

        return ['success' => true, 'email' => $rows[0]['email']];
    }

    /**
     * Verify a magic-link token.
     *
     * @return array{success: bool, email?: string, remember_me?: bool, error?: string}
     */
    public static function verifyMagicLink(string $tokenRaw): array
    {
        $hash = hash('sha256', $tokenRaw);

        $rows = Database::query(
            "SELECT `id`, `email`, `remember_me` FROM `login_tokens`
             WHERE `type` = 'magic_link' AND `token_hash` = ?
               AND `expires_at` > NOW() AND `used_at` IS NULL
             LIMIT 1",
            [$hash]
        );

        if (empty($rows)) {
            return ['success' => false, 'error' => 'invalid_token'];
        }

        // Mark as used (single-use)
        Database::execute(
            "UPDATE `login_tokens` SET `used_at` = NOW() WHERE `id` = ?",
            [$rows[0]['id']]
        );

        return [
            'success'     => true,
            'email'       => $rows[0]['email'],
            'remember_me' => (bool) $rows[0]['remember_me'],
        ];
    }

    // ── Password reset ──

    private const RESET_EXPIRY_MINUTES = 60;

    /**
     * Create a password-reset token for the given email.
     *
     * Does NOT invalidate previous tokens — the caller is responsible
     * for calling invalidatePasswordResets() after confirming the email
     * was sent, so a failed send never burns a user's working link.
     *
     * @return array{success: bool, token?: string, id?: string, error?: string}
     */
    public static function createPasswordReset(string $email, string $ip): array
    {
        $floodCheck = self::checkFloodLimit($email);
        if ($floodCheck !== null) {
            return $floodCheck;
        }

        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $id = Ulid::generate();

        Database::execute(
            "INSERT INTO `login_tokens` (`id`, `email`, `type`, `token_hash`, `remember_me`, `expires_at`, `ip_address`)
             VALUES (?, ?, 'password_reset', ?, 0, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)",
            [$id, $email, $hash, self::RESET_EXPIRY_MINUTES, $ip]
        );

        return ['success' => true, 'token' => $rawToken, 'id' => $id];
    }

    /**
     * Invalidate all unused password-reset tokens for an email except
     * the one just issued.
     *
     * Call this after the reset email has been successfully dispatched.
     */
    public static function invalidatePasswordResets(string $email, string $keepId): void
    {
        Database::execute(
            "UPDATE `login_tokens` SET `used_at` = NOW()
             WHERE `email` = ? AND `type` = 'password_reset'
               AND `used_at` IS NULL AND `id` != ?",
            [$email, $keepId]
        );
    }

    /**
     * Delete a specific token by ID (rollback path when email send fails).
     */
    public static function deleteToken(string $id): void
    {
        Database::execute(
            "DELETE FROM `login_tokens` WHERE `id` = ?",
            [$id]
        );
    }

    /**
     * Verify a password-reset token.
     *
     * @return array{success: bool, email?: string, error?: string}
     */
    public static function verifyPasswordReset(string $tokenRaw): array
    {
        $hash = hash('sha256', $tokenRaw);

        $rows = Database::query(
            "SELECT `id`, `email` FROM `login_tokens`
             WHERE `type` = 'password_reset' AND `token_hash` = ?
               AND `expires_at` > NOW() AND `used_at` IS NULL
             LIMIT 1",
            [$hash]
        );

        if (empty($rows)) {
            return ['success' => false, 'error' => 'invalid_token'];
        }

        Database::execute(
            "UPDATE `login_tokens` SET `used_at` = NOW() WHERE `id` = ?",
            [$rows[0]['id']]
        );

        return ['success' => true, 'email' => $rows[0]['email']];
    }

    /**
     * Check whether a password-reset token is valid without consuming it.
     *
     * Used by the GET handler to reject expired/invalid links before
     * rendering the password form.
     */
    public static function peekPasswordReset(string $tokenRaw): bool
    {
        $hash = hash('sha256', $tokenRaw);

        $rows = Database::query(
            "SELECT 1 FROM `login_tokens`
             WHERE `type` = 'password_reset' AND `token_hash` = ?
               AND `expires_at` > NOW() AND `used_at` IS NULL
             LIMIT 1",
            [$hash]
        );

        return !empty($rows);
    }

    /**
     * Delete expired and used tokens.
     */
    public static function cleanup(): void
    {
        Database::execute(
            "DELETE FROM `login_tokens` WHERE `expires_at` < NOW() OR `used_at` IS NOT NULL"
        );
    }

    /**
     * Check per-email flood limit (5 tokens per 15 min regardless of type/status).
     *
     * @return array{success: false, error: string}|null  Returns error array if limited, null if OK.
     */
    private static function checkFloodLimit(string $email): ?array
    {
        $recent = Database::query(
            "SELECT COUNT(*) as cnt FROM `login_tokens`
             WHERE `email` = ? AND `created_at` > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$email, self::FLOOD_WINDOW_MINUTES]
        );

        if (((int) ($recent[0]['cnt'] ?? 0)) >= self::FLOOD_LIMIT) {
            return ['success' => false, 'error' => 'rate_limited'];
        }

        return null;
    }
}
