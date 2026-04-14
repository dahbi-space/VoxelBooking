<?php

/**
 * PHPUnit test bootstrap.
 *
 * Ensures the test environment is consistent before any tests run:
 * 1. Autoloader loaded
 * 2. .env file exists (required by curl-based integration tests that
 *    hit the web server, where InstalledMiddleware reads .env)
 * 3. installed_at setting is present in the database (prevents
 *    cascading /install redirects in integration tests)
 */

declare(strict_types=1);

// 1. Autoloader
require __DIR__ . '/../vendor/autoload.php';

// 2. Ensure .env exists for the web server process
$envPath = dirname(__DIR__) . '/.env';
if (!is_file($envPath)) {
    $host     = $_ENV['DB_HOST']     ?? '127.0.0.1';
    $port     = $_ENV['DB_PORT']     ?? '3309';
    $database = $_ENV['DB_DATABASE'] ?? 'voxelbooking';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    file_put_contents($envPath, implode("\n", [
        'APP_NAME=VoxelBooking',
        'APP_DEBUG=false',
        'APP_TIMEZONE=UTC',
        'FORCE_HTTPS=true',
        'DB_CONNECTION=mysql',
        'DB_HOST=' . $host,
        'DB_PORT=' . $port,
        'DB_DATABASE=' . $database,
        'DB_USERNAME=' . $username,
        'DB_PASSWORD=' . $password,
    ]) . "\n", LOCK_EX);

    fwrite(STDERR, "[bootstrap] Created missing .env from phpunit.xml env vars\n");
}

// 3. Ensure installed_at exists in the database
try {
    $host     = $_ENV['DB_HOST']     ?? '127.0.0.1';
    $port     = $_ENV['DB_PORT']     ?? '3309';
    $database = $_ENV['DB_DATABASE'] ?? 'voxelbooking';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Check if settings table exists
    $tables = $pdo->query(
        "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = '{$database}' AND table_name = 'settings'"
    )->fetch(PDO::FETCH_ASSOC);

    if (($tables['cnt'] ?? 0) > 0) {
        $pdo->prepare(
            "INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES ('installed_at', ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = IF(`value` = '' OR `value` IS NULL, VALUES(`value`), `value`)"
        )->execute(['2026-03-27 10:00:00']);
    }
} catch (\Throwable $e) {
    // DB not reachable — integration tests will skip themselves
    fwrite(STDERR, "[bootstrap] DB check skipped: " . $e->getMessage() . "\n");
}
