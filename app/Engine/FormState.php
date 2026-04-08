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
 * All state is one-shot: consumed from the session on first access and
 * cached in static memory for the duration of the request. Subsequent
 * reads come from the static cache, ensuring both one-shot semantics
 * (page refresh = clean form) and multi-read safety (templates may call
 * old('name') multiple times without losing data).
 *
 * Usage (controller):
 *   FormState::flashInput(['name' => $name, ...]);
 *   FormState::flashErrors(['name' => 'Name is required']);
 *   FormState::toast('error', 'Validation failed.');
 *   return Response::redirect('/admin/...');
 *
 * Usage (template via helpers):
 *   value="<?= e(old('name', $entity['name'] ?? '')) ?>"
 *   class="vb-input <?= error_class('name') ?>"
 *   <?php if (has_error('name')): ?><div class="vb-form-error"><?= e(field_error('name')) ?></div><?php endif; ?>
 */
final class FormState
{
    private const KEY_OLD    = '_form_old';
    private const KEY_ERRORS = '_form_errors';
    private const KEY_TOAST  = '_form_toast';

    // ── Static caches (consume-on-first-access) ──

    /** @var array<string, mixed>|null null = not yet consumed from session */
    private static ?array $oldCache = null;

    /** @var array<string, string>|null null = not yet consumed from session */
    private static ?array $errorsCache = null;

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

    // ── Read (consume-on-first-access, then serve from cache) ──

    /**
     * Get ALL flashed old input as an array.
     *
     * Consumes from session on first call, returns cached data thereafter.
     * Use oldValue() for single-field access in templates.
     *
     * @return array<string, mixed>
     */
    public static function old(): array
    {
        if (self::$oldCache === null) {
            self::$oldCache = $_SESSION[self::KEY_OLD] ?? [];
            unset($_SESSION[self::KEY_OLD]);
        }
        return self::$oldCache;
    }

    /**
     * Get a single old input value with a fallback.
     *
     * Safe to call multiple times per render — first call consumes from
     * session, subsequent calls read from the static cache.
     */
    public static function oldValue(string $key, mixed $default = ''): mixed
    {
        // Ensure consumed from session into cache
        self::old();
        return self::$oldCache[$key] ?? $default;
    }

    /**
     * Get ALL flashed per-field errors as an array.
     *
     * Consumes from session on first call, returns cached data thereafter.
     *
     * @return array<string, string>
     */
    public static function errors(): array
    {
        if (self::$errorsCache === null) {
            self::$errorsCache = $_SESSION[self::KEY_ERRORS] ?? [];
            unset($_SESSION[self::KEY_ERRORS]);
        }
        return self::$errorsCache;
    }

    /**
     * Check if a specific field has an error.
     *
     * Safe to call multiple times — same consume-on-first-access pattern.
     */
    public static function hasError(string $field): bool
    {
        self::errors();
        return isset(self::$errorsCache[$field]);
    }

    /**
     * Get the error message for a specific field.
     *
     * Safe to call multiple times — same consume-on-first-access pattern.
     */
    public static function fieldError(string $field): string
    {
        self::errors();
        return self::$errorsCache[$field] ?? '';
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
     * Clear all form state from the session AND the static caches.
     */
    public static function clear(): void
    {
        unset(
            $_SESSION[self::KEY_OLD],
            $_SESSION[self::KEY_ERRORS],
            $_SESSION[self::KEY_TOAST]
        );
        self::$oldCache = null;
        self::$errorsCache = null;
    }
}
