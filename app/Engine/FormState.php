<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Unified form state engine for PRG (Post-Redirect-Get) flows.
 *
 * Provides a single API for flashing old input, per-field errors, and
 * toast messages across all admin form surfaces. Replaces the ad-hoc
 * $_SESSION['_old_input'], $_SESSION['_field_errors'],
 * $_SESSION['settings_old_input'], and $_SESSION['settings_flash']
 * patterns that were duplicated across 14 controllers.
 *
 * All state is one-shot: retrieved once, then cleared from the session.
 *
 * Usage (controller):
 *   FormState::flashInput(['name' => $name, ...]);
 *   FormState::flashErrors(['name' => 'Name is required']);
 *   FormState::toast('error', 'Validation failed.');
 *   return Response::redirect('/admin/...');
 *
 * Usage (template via helpers):
 *   value="<?= e(old('name', $default)) ?>"
 *   class="vb-input <?= error_class('name') ?>"
 */
final class FormState
{
    private const KEY_OLD    = '_form_old';
    private const KEY_ERRORS = '_form_errors';
    private const KEY_TOAST  = '_form_toast';

    // ── Write (flash into session for next request) ──

    /**
     * Flash old input values into the session.
     *
     * Call this before a redirect so the form re-populates on the
     * next GET request. Never flash raw $_POST — always build an
     * explicit array to avoid leaking CSRF tokens or hidden fields.
     */
    public static function flashInput(array $input): void
    {
        Auth::startSession();
        $_SESSION[self::KEY_OLD] = $input;
    }

    /**
     * Flash per-field error messages into the session.
     *
     * @param array<string, string> $errors Field name => error message
     */
    public static function flashErrors(array $errors): void
    {
        Auth::startSession();
        $_SESSION[self::KEY_ERRORS] = $errors;
    }

    /**
     * Flash both old input and per-field errors in one call.
     */
    public static function flash(array $input, array $errors = []): void
    {
        self::flashInput($input);
        if (!empty($errors)) {
            self::flashErrors($errors);
        }
    }

    /**
     * Flash a toast notification (success, error, info, warning).
     */
    public static function toast(string $type, string $message): void
    {
        Auth::startSession();
        $_SESSION[self::KEY_TOAST] = ['type' => $type, 'message' => $message];
    }

    // ── Read (retrieve and clear — one-shot) ──

    /**
     * Get flashed old input and clear it from the session.
     *
     * @return array<string, mixed>
     */
    public static function old(): array
    {
        $old = $_SESSION[self::KEY_OLD] ?? [];
        unset($_SESSION[self::KEY_OLD]);
        return $old;
    }

    /**
     * Get a single old input value with a fallback.
     */
    public static function oldValue(string $key, mixed $default = ''): mixed
    {
        // Peek without clearing — clearing happens in old()
        return $_SESSION[self::KEY_OLD][$key] ?? $default;
    }

    /**
     * Get flashed per-field errors and clear them from the session.
     *
     * @return array<string, string>
     */
    public static function errors(): array
    {
        $errors = $_SESSION[self::KEY_ERRORS] ?? [];
        unset($_SESSION[self::KEY_ERRORS]);
        return $errors;
    }

    /**
     * Check if a specific field has an error (peek, does not clear).
     */
    public static function hasError(string $field): bool
    {
        return isset($_SESSION[self::KEY_ERRORS][$field]);
    }

    /**
     * Get the error message for a specific field (peek, does not clear).
     */
    public static function fieldError(string $field): string
    {
        return $_SESSION[self::KEY_ERRORS][$field] ?? '';
    }

    /**
     * Get the flashed toast and clear it from the session.
     *
     * @return array{type: string, message: string}|null
     */
    public static function getToast(): ?array
    {
        $toast = $_SESSION[self::KEY_TOAST] ?? null;
        unset($_SESSION[self::KEY_TOAST]);
        return $toast;
    }

    // ── Utilities ──

    /**
     * Clear all form state from the session.
     */
    public static function clear(): void
    {
        unset(
            $_SESSION[self::KEY_OLD],
            $_SESSION[self::KEY_ERRORS],
            $_SESSION[self::KEY_TOAST]
        );
    }
}
