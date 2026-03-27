<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Application kernel.
 *
 * Boots the environment, registers error handlers, sets timezone,
 * registers routes, dispatches requests through middleware, returns responses.
 */
final class App
{
    private Router $router;
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->router = new Router();
    }

    /**
     * Boot the application: load env, init logger, set timezone, init view.
     */
    public function boot(): self
    {
        // Load environment
        EnvLoader::load($this->basePath . '/.env');

        // Initialize logger
        Logger::init($this->basePath . '/storage');

        // Register error handlers in production
        if (($this->env('APP_DEBUG', 'false')) !== 'true') {
            Logger::registerErrorHandlers();
        }

        // Set timezone
        $timezone = $this->env('APP_TIMEZONE', 'UTC');
        date_default_timezone_set($timezone);

        // Initialize view engine
        View::init($this->basePath . '/templates');

        return $this;
    }

    /**
     * Register application routes.
     */
    public function routes(callable $callback): self
    {
        $callback($this->router);

        return $this;
    }

    /**
     * Handle the incoming HTTP request.
     */
    public function handle(): void
    {
        $request = new Request();
        $response = $this->router->dispatch($request);
        $response->send();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function env(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    /**
     * Check if the application is installed.
     */
    public function isInstalled(): bool
    {
        // Check for .env with DB config
        if (empty($_ENV['DB_HOST'] ?? '')) {
            return false;
        }

        // Check database connection and settings table
        if (!Database::canConnect()) {
            return false;
        }

        if (!Database::tableExists('settings')) {
            return false;
        }

        // Check for installed_at value
        try {
            $result = Database::query(
                "SELECT `value` FROM `settings` WHERE `key` = 'installed_at'"
            );

            return !empty($result[0]['value'] ?? '');
        } catch (\Throwable) {
            return false;
        }
    }
}
