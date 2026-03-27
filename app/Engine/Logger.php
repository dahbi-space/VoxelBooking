<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * File-based application logging.
 *
 * Writes to storage/logs/app.log. Format: [YYYY-MM-DD HH:MM:SS] LEVEL: message.
 * Registers as PHP error handler and exception handler in production.
 * Log rotation: when app.log exceeds 5MB, renamed to app.log.1 (max 3 rotated files).
 */
final class Logger
{
    private static string $logPath = '';
    private static int $maxSize = 5 * 1024 * 1024; // 5MB
    private static int $maxFiles = 3;

    public static function init(string $storagePath): void
    {
        self::$logPath = rtrim($storagePath, '/') . '/logs/app.log';

        $logDir = dirname(self::$logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function registerErrorHandlers(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        self::error("{$errstr} in {$errfile}:{$errline}");
        return true;
    }

    public static function handleException(\Throwable $e): void
    {
        self::error(
            $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
            ['trace' => $e->getTraceAsString()]
        );

        if (!headers_sent()) {
            http_response_code(500);
        }

        $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        if ($debug) {
            echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
        } else {
            echo 'Something went wrong.';
        }
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (self::$logPath === '') {
            return;
        }

        self::rotate();

        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] {$level}: {$message}";

        if (!empty($context)) {
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        $entry .= PHP_EOL;

        file_put_contents(self::$logPath, $entry, FILE_APPEND | LOCK_EX);
    }

    private static function rotate(): void
    {
        if (!is_file(self::$logPath)) {
            return;
        }

        if (filesize(self::$logPath) < self::$maxSize) {
            return;
        }

        // Shift existing rotated files
        for ($i = self::$maxFiles; $i >= 1; $i--) {
            $older = self::$logPath . '.' . $i;
            if ($i === self::$maxFiles && is_file($older)) {
                unlink($older);
            }
            $newer = ($i === 1) ? self::$logPath : self::$logPath . '.' . ($i - 1);
            if (is_file($newer)) {
                rename($newer, self::$logPath . '.' . $i);
            }
        }
    }
}
