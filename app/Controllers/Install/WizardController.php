<?php

declare(strict_types=1);

namespace App\Controllers\Install;

use App\Engine\Database;
use App\Engine\EnvWriter;
use App\Engine\Flash;
use App\Engine\Migrator;
use App\Engine\Request;
use App\Engine\Response;
use App\Engine\Ulid;
use App\Engine\Validator;
use App\Engine\Version;
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
use PDO;

/**
 * 5-step installation wizard controller.
 *
 * Step 1: System requirements (read-only)
 * Step 2: Database configuration (writes .env, runs migrations)
 * Step 3: Email configuration (optional, skippable)
 * Step 4: Operator account creation
 * Step 5: First tenant creation (optional, skippable)
 * Complete: writes installed_at, shows success
 */
final class WizardController
{
    public function show(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $step = $request->query('step', '1');

        if ($step === 'complete') {
            return View::response('install.wizard', [
                'step' => 'complete',
                'checks' => [],
                'errors' => [],
                'flash' => Flash::get(),
                'session' => $_SESSION['install'] ?? [],
                'csrfToken' => CsrfMiddleware::generateToken(),
            ]);
        }

        $step = max(1, min(5, (int) $step));

        $data = [
            'step' => $step,
            'checks' => $this->runSystemChecks(),
            'errors' => [],
            'flash' => Flash::get(),
            'session' => $_SESSION['install'] ?? [],
            'csrfToken' => CsrfMiddleware::generateToken(),
        ];

        return View::response('install.wizard', $data);
    }

    public function stepTwo(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $errors = Validator::validate($request->all(), [
            'db_host'     => 'required',
            'db_port'     => 'required|integer',
            'db_database' => 'required',
            'db_username' => 'required',
        ]);

        if (!empty($errors)) {
            return View::response('install.wizard', [
                'step' => 2,
                'checks' => $this->runSystemChecks(),
                'errors' => $errors,
                'flash' => [],
                'session' => $_SESSION['install'] ?? [],
                'csrfToken' => CsrfMiddleware::generateToken(),
            ]);
        }

        $host = $request->string('db_host');
        $port = $request->string('db_port');
        $database = $request->string('db_database');
        $username = $request->string('db_username');
        $password = $request->string('db_password');

        // Test MySQL connection
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $testPdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $version = $testPdo->query('SELECT VERSION()')->fetchColumn();
            $testPdo = null;
        } catch (\PDOException $e) {
            $errorMsg = $this->translateDbError($e);

            return View::response('install.wizard', [
                'step' => 2,
                'checks' => $this->runSystemChecks(),
                'errors' => ['db_connection' => $errorMsg],
                'flash' => [],
                'session' => $_SESSION['install'] ?? [],
                'csrfToken' => CsrfMiddleware::generateToken(),
            ]);
        }

        // Detect HTTPS
        $isSecure = $request->isSecure();
        $protocol = $isSecure ? 'https' : 'http';
        $appUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        // Write .env
        $basePath = dirname(__DIR__, 3);
        $envPath = $basePath . '/.env';

        EnvWriter::setMultiple($envPath, [
            'APP_NAME'     => $_ENV['APP_NAME'] ?? 'VoxelBooking',
            'APP_URL'      => $appUrl,
            'APP_DEBUG'    => 'false',
            'APP_TIMEZONE' => 'UTC',
            'FORCE_HTTPS'  => $isSecure ? 'true' : 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'      => $host,
            'DB_PORT'      => $port,
            'DB_DATABASE'  => $database,
            'DB_USERNAME'  => $username,
            'DB_PASSWORD'  => $password,
        ]);

        // Reload environment
        $_ENV['DB_HOST'] = $host;
        $_ENV['DB_PORT'] = $port;
        $_ENV['DB_DATABASE'] = $database;
        $_ENV['DB_USERNAME'] = $username;
        $_ENV['DB_PASSWORD'] = $password;
        $_ENV['APP_URL'] = $appUrl;

        // Reset DB connection to use new credentials
        Database::reset();

        // Run migrations
        try {
            $migrator = new Migrator($basePath . '/app/Migrations');
            $count = $migrator->migrate();

            $_SESSION['install']['db_configured'] = true;
            $_SESSION['install']['db_version'] = $migrator->getCurrentVersion();
            $_SESSION['install']['mysql_version'] = $version;
            $_SESSION['install']['migrations_run'] = $count;

            Flash::set('success', str_replace([':version', ':count'], [$version, $count], __('install.flash.db_connected')));

            return Response::redirect('/install?step=3');
        } catch (\Throwable $e) {
            return View::response('install.wizard', [
                'step' => 2,
                'checks' => $this->runSystemChecks(),
                'errors' => ['db_migration' => str_replace(':error', $e->getMessage(), __('install.flash.migration_failed'))],
                'flash' => [],
                'session' => $_SESSION['install'] ?? [],
                'csrfToken' => CsrfMiddleware::generateToken(),
            ]);
        }
    }

    public function stepThree(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $skip = $request->string('skip');

        if ($skip === '1') {
            $_SESSION['install']['mail_configured'] = false;
            Flash::set('info', __('install.flash.email_skipped'));

            return Response::redirect('/install?step=4');
        }

        $errors = Validator::validate($request->all(), [
            'mail_host'         => 'required',
            'mail_port'         => 'required|integer',
            'mail_from_address' => 'required|email',
            'mail_from_name'    => 'required|max_length:255',
        ]);

        if (!empty($errors)) {
            return View::response('install.wizard', [
                'step' => 3,
                'checks' => [],
                'errors' => $errors,
                'flash' => [],
                'session' => $_SESSION['install'] ?? [],
                'csrfToken' => CsrfMiddleware::generateToken(),
            ]);
        }

        // Store mail settings in the database
        $mailSettings = [
            'mail_host'         => $request->string('mail_host'),
            'mail_port'         => $request->string('mail_port'),
            'mail_username'     => $request->string('mail_username'),
            'mail_password'     => $request->string('mail_password'),
            'mail_encryption'   => $request->string('mail_encryption', 'tls'),
            'mail_from_address' => $request->string('mail_from_address'),
            'mail_from_name'    => $request->string('mail_from_name'),
        ];

        foreach ($mailSettings as $key => $value) {
            $this->setSetting($key, $value);
        }

        $_SESSION['install']['mail_configured'] = true;
        Flash::set('success', __('install.flash.email_saved'));

        return Response::redirect('/install?step=4');
    }

    public function stepFour(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $errors = Validator::validate($request->all(), [
            'name'     => 'required|max_length:255',
            'email'    => 'required|email',
            'password' => 'required|min:8',
        ]);

        $password = $request->string('password');
        $confirm = $request->string('password_confirmation');

        if ($password !== $confirm) {
            $errors['password_confirmation'] = __('install.flash.passwords_mismatch');
        }

        if (!empty($errors)) {
            return View::response('install.wizard', [
                'step' => 4,
                'checks' => [],
                'errors' => $errors,
                'flash' => [],
                'session' => $_SESSION['install'] ?? [],
                'csrfToken' => CsrfMiddleware::generateToken(),
            ]);
        }

        $operatorId = Ulid::generate();
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        Database::execute(
            'INSERT INTO `operators` (`id`, `name`, `email`, `password_hash`) VALUES (?, ?, ?, ?)',
            [$operatorId, $request->string('name'), $request->string('email'), $passwordHash]
        );

        // Store global settings
        $timezone = $request->string('timezone', 'UTC') ?: 'UTC';
        $locale = $request->string('locale', 'en') ?: 'en';

        $this->setSetting('operator_email', $request->string('email'));
        $defaultName = $_ENV['APP_NAME'] ?? 'VoxelBooking';
        $this->setSetting('app_name', $request->string('app_name', $defaultName) ?: $defaultName);
        $this->setSetting('default_timezone', $timezone);
        $this->setSetting('default_locale', $locale);
        $this->setSetting('cron_secret', bin2hex(random_bytes(32)));
        $this->setSetting('version', Version::get());

        $_SESSION['install']['operator_created'] = true;
        $_SESSION['install']['operator_id'] = $operatorId;
        $_SESSION['install']['operator_email'] = $request->string('email');

        Flash::set('success', __('install.flash.operator_created'));

        return Response::redirect('/install?step=5');
    }

    public function stepFive(Request $request): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $skip = $request->string('skip');

        if ($skip === '1') {
            return $this->finishInstallation();
        }

        $errors = Validator::validate($request->all(), [
            'name'            => 'required|max_length:255',
            'email'           => 'required|email',
            'booking_pattern' => 'required|in:timeslot,resource,capacity,event',
            'brand_color'     => 'required|max_length:7',
        ]);

        if (!empty($errors)) {
            return View::response('install.wizard', [
                'step' => 5,
                'checks' => [],
                'errors' => $errors,
                'flash' => [],
                'session' => $_SESSION['install'] ?? [],
                'csrfToken' => CsrfMiddleware::generateToken(),
            ]);
        }

        $tenantId = Ulid::generate();
        $slug = $this->generateSlug($request->string('name'));
        $brandColor = $request->string('brand_color', '#2563EB');

        Database::execute(
            "INSERT INTO `tenants` (`id`, `slug`, `name`, `email`, `booking_pattern`, `brand_color`, `brand_color_text`, `timezone`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $slug,
                $request->string('name'),
                $request->string('email'),
                $request->string('booking_pattern'),
                $brandColor,
                $this->contrastColor($brandColor),
                $_SESSION['install']['timezone'] ?? 'UTC',
            ]
        );

        // Create upload directory
        $basePath = dirname(__DIR__, 3);
        $uploadDir = $basePath . '/public/uploads/' . $slug;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $_SESSION['install']['tenant_created'] = true;
        $_SESSION['install']['tenant_slug'] = $slug;
        $_SESSION['install']['tenant_name'] = $request->string('name');

        return $this->finishInstallation();
    }

    public function complete(Request $request): Response
    {
        return $this->finishInstallation();
    }

    // ── Private helpers ──

    private function finishInstallation(): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->setSetting('installed_at', date('Y-m-d H:i:s'));

        Flash::set('success', __('install.flash.install_complete'));

        return Response::redirect('/install?step=complete');
    }

    /**
     * @return array<int, array{name: string, required: bool, passed: bool, message: string}>
     */
    private function runSystemChecks(): array
    {
        $basePath = dirname(__DIR__, 3);

        return [
            [
                'name'     => __('install.checks.php_version'),
                'required' => true,
                'passed'   => version_compare(PHP_VERSION, '8.3.0', '>='),
                'message'  => version_compare(PHP_VERSION, '8.3.0', '>=')
                    ? str_replace(':version', PHP_VERSION, __('install.checks.php_ok'))
                    : str_replace(':version', PHP_VERSION, __('install.checks.php_fail')),
            ],
            [
                'name'     => __('install.checks.pdo_mysql'),
                'required' => true,
                'passed'   => extension_loaded('pdo_mysql'),
                'message'  => extension_loaded('pdo_mysql')
                    ? __('install.checks.loaded')
                    : str_replace(':ext', 'pdo_mysql', __('install.checks.enable_ext')),
            ],
            [
                'name'     => __('install.checks.curl'),
                'required' => true,
                'passed'   => extension_loaded('curl'),
                'message'  => extension_loaded('curl')
                    ? __('install.checks.loaded')
                    : str_replace(':ext', 'curl', __('install.checks.enable_ext')),
            ],
            [
                'name'     => __('install.checks.mbstring'),
                'required' => true,
                'passed'   => extension_loaded('mbstring'),
                'message'  => extension_loaded('mbstring')
                    ? __('install.checks.loaded')
                    : str_replace(':ext', 'mbstring', __('install.checks.enable_ext')),
            ],
            [
                'name'     => __('install.checks.json'),
                'required' => true,
                'passed'   => extension_loaded('json'),
                'message'  => extension_loaded('json')
                    ? __('install.checks.loaded')
                    : str_replace(':ext', 'json', __('install.checks.enable_ext')),
            ],
            [
                'name'     => __('install.checks.fileinfo'),
                'required' => true,
                'passed'   => extension_loaded('fileinfo'),
                'message'  => extension_loaded('fileinfo')
                    ? __('install.checks.loaded')
                    : str_replace(':ext', 'fileinfo', __('install.checks.enable_ext')),
            ],
            [
                'name'     => __('install.checks.openssl'),
                'required' => true,
                'passed'   => extension_loaded('openssl'),
                'message'  => extension_loaded('openssl')
                    ? __('install.checks.loaded')
                    : str_replace(':ext', 'openssl', __('install.checks.enable_ext')),
            ],
            [
                'name'     => __('install.checks.gd'),
                'required' => true,
                'passed'   => extension_loaded('gd'),
                'message'  => extension_loaded('gd')
                    ? __('install.checks.loaded')
                    : str_replace(':ext', 'gd', __('install.checks.enable_ext')),
            ],
            [
                'name'     => __('install.checks.storage_logs'),
                'required' => true,
                'passed'   => is_writable($basePath . '/storage/logs'),
                'message'  => is_writable($basePath . '/storage/logs')
                    ? __('install.checks.writable')
                    : str_replace(':path', 'storage/logs', __('install.checks.chmod')),
            ],
            [
                'name'     => __('install.checks.public_uploads'),
                'required' => true,
                'passed'   => is_writable($basePath . '/public/uploads'),
                'message'  => is_writable($basePath . '/public/uploads')
                    ? __('install.checks.writable')
                    : str_replace(':path', 'public/uploads', __('install.checks.chmod')),
            ],
        ];
    }

    private function translateDbError(\PDOException $e): string
    {
        $code = (int) $e->getCode();
        $msg = $e->getMessage();

        if (str_contains($msg, 'Access denied')) {
            return __('install.db_errors.access_denied');
        }

        if (str_contains($msg, 'Unknown database')) {
            return __('install.db_errors.unknown_database');
        }

        if (str_contains($msg, 'Connection refused')) {
            return __('install.db_errors.connection_refused');
        }

        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
            return __('install.db_errors.timed_out');
        }

        return str_replace(':message', $msg, __('install.db_errors.generic'));
    }

    private function setSetting(string $key, string $value): void
    {
        Database::execute(
            "INSERT INTO `settings` (`key`, `value`, `updated_at`)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()",
            [$key, $value, $value]
        );
    }

    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug) ?? $slug;
        $slug = preg_replace('/[\s-]+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'business';
        }

        // Ensure uniqueness
        $baseSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $result = Database::query(
            'SELECT COUNT(*) as cnt FROM `tenants` WHERE `slug` = ?',
            [$slug]
        );

        return ($result[0]['cnt'] ?? 0) > 0;
    }

    /**
     * Returns white or dark text color based on background luminance.
     * Simplified contrast calculation for installation wizard.
     */
    private function contrastColor(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return '#FFFFFF';
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        // sRGB to linear
        $r = ($r <= 0.03928) ? $r / 12.92 : (($r + 0.055) / 1.055) ** 2.4;
        $g = ($g <= 0.03928) ? $g / 12.92 : (($g + 0.055) / 1.055) ** 2.4;
        $b = ($b <= 0.03928) ? $b / 12.92 : (($b + 0.055) / 1.055) ** 2.4;

        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luminance > 0.179 ? '#1A1A2E' : '#FFFFFF';
    }
}
