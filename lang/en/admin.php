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
        'title'             => 'Dashboard',
        'upcoming_bookings' => 'Upcoming bookings',
        'today'             => 'Today',
        'this_week'         => 'This week',
        'no_bookings'       => 'No upcoming bookings.',
    ],

    // ── Settings ──
    'settings' => [
        'general_title' => 'General',
        'account_title' => 'Account',
        'email_title'   => 'Email',
        'cron_title'    => 'Cron',
        'logs_title'    => 'Logs',
        'saved'         => 'Settings saved.',
        'save_button'   => 'Save changes',
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
