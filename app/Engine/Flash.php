<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Session-based flash messages.
 *
 * Messages persist for exactly one request (set on this request, read on next).
 * Types: success, error, warning, info.
 */
final class Flash
{
    public static function set(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Get and clear all flash messages.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public static function get(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $messages;
    }

    public static function has(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return !empty($_SESSION['_flash']);
    }
}
