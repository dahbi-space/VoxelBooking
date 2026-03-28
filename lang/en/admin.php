<?php

declare(strict_types=1);

/**
 * English translations: admin panel.
 */
return [
    // ── Navigation ──
    'nav' => [
        'dashboard'      => 'Dashboard',
        'bookings'       => 'Bookings',
        'all_bookings'   => 'All Bookings',
        'services'       => 'Services',
        'staff'          => 'Staff',
        'customers'      => 'Customers',
        'tenants'        => 'Tenants',
        'settings'       => 'Settings',
        'audit_log'      => 'Audit log',
        'system'         => 'System',
        'toggle_theme'   => 'Toggle theme',
        'toggle_sidebar' => 'Toggle sidebar',
        'sign_out'       => 'Sign out',
    ],

    // ── Dashboard ──
    'dashboard' => [
        'title'               => 'Dashboard',
        'active_tenants'      => 'Active Tenants',
        'bookings_today'      => 'Bookings Today',
        'this_week'           => 'This Week',
        'upcoming_24h'        => 'Upcoming (24h)',
        'no_change'           => 'No change',
        'awaiting_first'      => 'Awaiting first booking',
        'no_data_yet'         => 'No data yet',
        'no_upcoming'         => 'No upcoming',
        'welcome_title'       => 'Welcome to VoxelBooking',
        'welcome_desc'        => 'Create your first tenant to start managing bookings. Each tenant represents a business — a salon, restaurant, clinic, or any service provider you manage.',
        'create_first_tenant' => 'Create your first tenant',
        'step_create'         => 'Create tenant',
        'step_configure'      => 'Configure services',
        'step_share'          => 'Share booking page',
    ],

    // ── Settings Tabs ──
    'tabs' => [
        'general'  => 'General',
        'account'  => 'Account',
        'email'    => 'Email',
        'cron'     => 'Cron',
        'logs'     => 'Logs',
        'audit'    => 'Audit Log',
        'aria'     => 'Settings',
    ],

    // ── Settings: General ──
    'settings' => [
        'general_title'      => 'General',
        'account_title'      => 'Account',
        'email_title'        => 'Email',
        'cron_title'         => 'Cron',
        'logs_title'         => 'Logs',
        'saved'              => 'Settings saved.',
        'save_button'        => 'Save changes',
        'app_title'          => 'Application',
        'app_desc'           => 'Core application configuration.',
        'app_name_label'     => 'Application name',
        'app_url_label'      => 'Application URL',
        'timezone_label'     => 'Timezone',
        'date_format_label'  => 'Date format',
        'system_title'       => 'System Information',
        'system_desc'        => 'Runtime environment and version details.',
        'memory_limit'       => 'Memory limit',
        'server'             => 'Server',
        'database'           => 'Database',
    ],

    // ── Settings: Account ──
    'account' => [
        'details_title'      => 'Account Details',
        'details_desc'       => 'Your operator account information.',
        'name_label'         => 'Name',
        'email_label'        => 'Email',
        'role_label'         => 'Role',
        'change_pw_title'    => 'Change Password',
        'change_pw_desc'     => 'Update your account password. Minimum 8 characters.',
        'current_pw_label'   => 'Current password',
        'new_pw_label'       => 'New password',
        'confirm_pw_label'   => 'Confirm new password',
        'update_pw_button'   => 'Update password',
    ],

    // ── Settings: Email ──
    'email' => [
        'smtp_title'         => 'SMTP Configuration',
        'smtp_desc'          => 'Outgoing email server settings for notifications and reminders.',
        'transport_label'    => 'Transport',
        'transport_smtp'     => 'SMTP — deliver via mail server',
        'transport_mailpit'  => 'Mailpit — deliver to localhost:1025 (dev/testing)',
        'transport_log'      => 'Log only — record to email_log, do not send',
        'host_label'         => 'SMTP host',
        'port_label'         => 'Port',
        'username_label'     => 'Username',
        'password_label'     => 'Password',
        'password_hint'      => 'Leave blank to keep current',
        'encryption_label'   => 'Encryption',
        'tls_recommended'    => 'TLS (recommended)',
        'sender_title'       => 'Sender Identity',
        'sender_desc'        => 'The "From" name and address that recipients will see.',
        'from_name_label'    => 'From name',
        'from_address_label' => 'From address',
        'save_button'        => 'Save email settings',
    ],

    // ── Settings: Cron ──
    'cron' => [
        'job_title'          => 'Cron Job',
        'job_desc'           => 'Add this command to your server\'s crontab (every 5 minutes recommended).',
        'command_label'      => 'Crontab command',
        'command_hint'       => 'Click to select, then copy.',
        'token_label'        => 'Cron token',
        'token_hint'         => 'Auto-generated. Keep this token secret.',
        'status_title'       => 'Status',
        'status_desc'        => 'Cron job execution history.',
        'last_run'           => 'Last run',
        'never_run'          => 'Never — cron has not run yet',
        'status_label'       => 'Status',
        'active'             => 'Active',
        'not_configured'     => 'Not configured',
    ],

    // ── Settings: Logs ──
    'logs' => [
        'title'              => 'Application Log',
        'desc'               => 'Last 100 lines from',
        'empty_title'        => 'No log entries yet',
        'empty_desc'         => 'Log entries will appear here as the system operates.',
    ],

    // ── Settings: Audit ──
    'audit' => [
        'title'              => 'Audit Log',
        'desc_suffix'        => 'entries · Structured event log for accountability and compliance',
        'all_events'         => 'All events',
        'empty_title'        => 'No audit log entries',
        'empty_filter'       => 'matching this filter',
        'empty_desc'         => 'Audit entries are created automatically when actions like logins, settings changes, and booking operations occur.',
        'th_time'            => 'Time',
        'th_event'           => 'Event',
        'th_actor'           => 'Actor',
        'th_entity'          => 'Entity',
        'th_details'         => 'Details',
        'th_request'         => 'Request',
        'page_of'            => 'Page :page of :total',
        'previous'           => 'Previous',
        'next'               => 'Next',
    ],

    // ── Deletion Queue ──
    'deletion' => [
        'title'              => 'Deletion Queue',
        'pending_title'      => 'Pending Requests',
        'empty_title'        => 'No pending requests',
        'empty_desc'         => 'All customer deletion requests have been processed.',
        'th_customer'        => 'Customer',
        'th_tenant'          => 'Tenant',
        'th_requested'       => 'Requested',
        'th_bookings'        => 'Bookings',
        'th_actions'         => 'Actions',
        'th_processed'       => 'Processed',
        'th_status'          => 'Status',
        'btn_anonymize'       => 'Anonymize',
        'btn_confirm'         => 'Confirm',
        'btn_cancel'          => 'Cancel',
        'btn_dismiss'         => 'Dismiss',
        'recently_processed'  => 'Recently Processed',
        'status_anonymized'   => 'Anonymized',
        'page_title'          => 'Deletion Queue — VoxelBooking',
        'missing_customer_id' => 'Missing customer ID.',
        'not_found'           => 'Customer not found or no pending deletion request.',
        'anonymized_success'  => 'Customer data has been anonymized. Deletion request processed.',
        'anonymize_failed'    => 'Anonymization failed: :reason',
        'confirm_failed'      => 'Failed to process deletion: :error',
        'dismissed_success'   => 'Deletion request dismissed. Customer data retained.',
        'dismiss_race'        => 'Request was already processed by another operator.',
        'dismiss_failed'      => 'Failed to dismiss deletion request.',
        'no_reason'           => 'No reason provided',
    ],

    // ── Common ──
    'common' => [
        'save'       => 'Save',
        'cancel'     => 'Cancel',
        'delete'     => 'Delete',
        'edit'       => 'Edit',
        'create'     => 'Create',
        'search'     => 'Search…',
        'actions'    => 'Actions',
        'confirm'    => 'Are you sure?',
        'loading'    => 'Loading…',
        'no_results' => 'No results found.',
    ],

    // ── Flash messages ──
    'flash' => [
        'general_saved'       => 'General settings saved.',
        'general_failed'      => 'Failed to save settings. Please try again.',
        'email_saved'         => 'Email settings saved.',
        'email_failed'        => 'Failed to save email settings. Please try again.',
        'password_updated'    => 'Password updated successfully.',
        'password_failed'     => 'Failed to update password. Please try again.',
        'password_required'   => 'All password fields are required.',
        'password_mismatch'   => 'New passwords do not match.',
        'password_min_length' => 'New password must be at least 8 characters.',
        'password_incorrect'  => 'Current password is incorrect.',
    ],

    // ── Error pages ──
    'errors' => [
        '403_page_title'   => '403 — VoxelBooking',
        '403_title'        => 'Access denied',
        '403_desc'         => 'You don\'t have permission to view this page.',
        '403_desc_admin'   => 'You don\'t have permission to view this page. Contact your administrator if you believe this is an error.',
        '403_action'       => 'Back to dashboard',
        '403_admin_title'  => 'Access Denied',
        '404_page_title'   => '404 — VoxelBooking',
        '404_title'        => 'Page not found',
        '404_desc'         => 'The page you\'re looking for doesn\'t exist.',
        '404_desc_admin'   => 'The page you\'re looking for doesn\'t exist or has been moved.',
        '404_admin_title'  => 'Not Found',
        '429_page_title'   => '429 — VoxelBooking',
        '429_title'        => 'Too many requests',
        '429_desc'         => 'Please wait a moment and try again.',
        '500_page_title'   => '500 — VoxelBooking',
        '500_title'        => 'Something went wrong',
        '500_desc'         => 'We\'ve logged the error. Try refreshing the page.',
        '500_action'       => 'Refresh page',
    ],
];
