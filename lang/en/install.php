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

    // ── Wizard Template ──
    'wizard' => [
        'page_title'             => 'Install — VoxelBooking',
        'complete_page_title'    => 'Installation Complete',

        // Step-bar short names (progress bar)
        'step_bar_1'             => 'System Check',
        'step_bar_2'             => 'Database',
        'step_bar_3'             => 'Email',
        'step_bar_4'             => 'Account',
        'step_bar_5'             => 'First Business',
        'step_of'                => 'Step :step of :total',
        'continue'               => 'Continue',

        // Step 1: System Requirements
        'step1_title'            => 'System Requirements',

        // Step 2: Database
        'step2_title'            => 'Database Configuration',
        'db_host'                => 'MySQL Host',
        'db_port'                => 'Port',
        'db_name'                => 'Database Name',
        'db_username'            => 'Username',
        'db_password'            => 'Password',
        'db_password_hint'       => 'Leave empty if none required.',
        'db_submit'              => 'Test Connection & Continue',

        // Step 3: Email
        'step3_title'            => 'Email Configuration',
        'mail_host'              => 'SMTP Host',
        'mail_port'              => 'Port',
        'mail_username'          => 'Username',
        'mail_password'          => 'Password',
        'mail_encryption'        => 'Encryption',
        'mail_from_address'      => 'From Address',
        'mail_from_name'         => 'From Name',
        'mail_submit'            => 'Save & Continue',
        'mail_skip'              => 'Skip for now',

        // Step 4: Operator
        'step4_title'            => 'Create Your Account',
        'op_name'                => 'Name',
        'op_email'               => 'Email',
        'op_password'            => 'Password',
        'op_password_hint'       => 'Minimum 8 characters.',
        'op_password_confirm'    => 'Confirm Password',
        'op_submit'              => 'Create Account & Continue',

        // Step 5: First Tenant
        'step5_title'            => 'Create Your First Business',
        'tenant_name'            => 'Business Name',
        'tenant_pattern'         => 'Booking Pattern',
        'pattern_timeslot'       => 'Time Slots',
        'pattern_timeslot_desc'  => 'Salon, therapist, tutor',
        'pattern_resource'       => 'Resources',
        'pattern_resource_desc'  => 'B&B, hotel, meeting room',
        'pattern_capacity'       => 'Capacity',
        'pattern_capacity_desc'  => 'Restaurant, escape room',
        'pattern_event'          => 'Events',
        'pattern_event_desc'     => 'Yoga, cooking class',
        'tenant_email'           => 'Business Email',
        'tenant_brand_color'     => 'Brand Color',
        'tenant_submit'          => 'Create Business & Finish',
        'tenant_skip'            => 'Skip — I\'ll add one later',

        // Complete step
        'complete_title'         => 'Installation Complete',
        'ready_message'          => 'VoxelBooking is ready to accept bookings.',
        'go_to_dashboard'        => 'Go to Dashboard',
    ],
];
