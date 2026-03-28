<?php

declare(strict_types=1);

/**
 * English translations: installation wizard.
 */
return [
    // ── Flash Messages ──
    'flash' => [
        'db_connected'        => 'Database connected. MySQL :version. :count table(s) created.',
        'email_skipped'       => 'Email configuration skipped. You can set this up later in Settings.',
        'email_saved'         => 'Email configuration saved.',
        'operator_created'    => 'Operator account created.',
        'install_complete'    => 'Installation complete.',
        'migration_failed'    => 'Migration failed: :error',
        'passwords_mismatch'  => 'Passwords do not match.',
    ],

    // ── Database Errors ──
    'db_errors' => [
        'access_denied'       => 'Access denied. Check your username and password.',
        'unknown_database'    => 'Database not found. Create it first, then try again.',
        'connection_refused'  => 'Connection refused. Is MySQL running on the specified host and port?',
        'timed_out'           => 'Connection timed out. Check the host address and port.',
        'generic'             => 'Database error: :message',
    ],

    // ── System Checks ──
    'checks' => [
        'php_version'         => 'PHP Version',
        'php_ok'              => 'PHP :version',
        'php_fail'            => 'PHP 8.3+ required. Current: :version',
        'pdo_mysql'           => 'PDO MySQL Extension',
        'curl'                => 'cURL Extension',
        'mbstring'            => 'mbstring Extension',
        'json'                => 'JSON Extension',
        'fileinfo'            => 'Fileinfo Extension',
        'openssl'             => 'OpenSSL Extension',
        'gd'                  => 'GD Extension',
        'loaded'              => 'Loaded',
        'enable_ext'          => 'Enable the :ext extension in your php.ini',
        'storage_logs'        => 'storage/logs writable',
        'public_uploads'      => 'public/uploads writable',
        'writable'            => 'Writable',
        'chmod'               => 'chmod 755 :path',
    ],
];
