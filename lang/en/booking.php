<?php

declare(strict_types=1);

/**
 * English translations: public booking flow.
 *
 * Key convention: section.element
 * Placeholders: :name (replaced at runtime)
 * Plurals: {0} None|{1} One|[2,*] :count items
 */
return [
    // ── Step Titles ──
    'steps' => [
        'service_title'    => 'Choose a service',
        'staff_title'      => 'Who would you like?',
        'staff_subtitle'   => 'Pick a team member, or let us assign whoever is available first.',
        'date_title'       => 'Pick a date',
        'time_title'       => 'Pick a time',
        'details_title'    => 'Your details',
        'details_subtitle' => 'We\'ll send a confirmation to your email.',
        'confirm_title'    => 'Confirm your booking',
        'confirm_subtitle' => 'Please review the details below.',
        // Resource-pattern
        'resource_title'    => 'Choose a room',
        'dates_title'       => 'Select dates',
        'dates_subtitle'    => 'Pick your check-in and check-out dates.',
        'guests_title'      => 'Number of guests',
    ],

    // ── Staff ──
    'staff' => [
        'any_available' => 'Any available',
    ],

    // ── Back Navigation ──
    'back' => [
        'change_service'     => '← Change service',
        'change_staff'       => '← Change team member',
        'change_date'        => '← Change date or time',
        'edit_details'       => '← Edit details',
    ],

    // ── Form Labels ──
    'form' => [
        'name_label'        => 'Name',
        'name_placeholder'  => 'Your name',
        'email_label'       => 'Email',
        'email_placeholder' => 'you@example.com',
        'phone_label'       => 'Phone',
        'phone_placeholder' => 'Your phone number',
        'notes_label'       => 'Notes',
        'notes_placeholder' => 'Any special requests?',
        'consent_default'   => 'I agree to the processing of my personal data for this booking.',
        'privacy_link'      => 'Privacy policy',
    ],

    // ── Buttons ──
    'buttons' => [
        'review'           => 'Review booking',
        'confirm'          => 'Confirm booking',
        'book_another'     => 'Book another appointment',
        'add_to_calendar'  => 'Add to Google Calendar',
        'download_ics'     => 'Download for Calendar',
        'reschedule'       => 'Reschedule',
        'cancel_booking'   => 'Cancel booking',
        'pick_another_time'=> 'Pick another time',
    ],

    // ── Confirmation ──
    'confirmed' => [
        'heading'           => 'Booking confirmed',
        'message'           => 'A confirmation has been sent to :email.',
        'email_sent'        => 'A confirmation has been sent to :email.',
        'reference_label'   => 'Reference',
    ],

    // ── Review ──
    'review' => [
        'cancellation_policy_label' => 'Cancellation policy',
    ],

    // ── Summary ──
    'summary' => [
        'service_label'    => 'Service',
        'with_label'       => 'With',
        'date_label'       => 'Date',
        'time_label'       => 'Time',
        'duration_label'   => 'Duration',
        'price_label'      => 'Price',
        // Resource-pattern
        'resource_label'   => 'Room',
        'check_in_label'   => 'Check-in',
        'check_out_label'  => 'Check-out',
        'nights_label'     => 'Nights',
        'guests_label'     => 'Guests',
        'total_label'      => 'Total',
        'per_night'        => '/night',
    ],

    // ── Resource Pattern ──
    'resource' => [
        'summary_resource'  => 'Room',
        'check_in_label'    => 'Check-in',
        'check_out_label'   => 'Check-out',
        'nights_label'      => 'Nights',
        'guests_label'      => 'Guests',
        'total_label'       => 'Total',
        'per_night'         => '/night',
        'select_check_in'   => 'Select check-in date',
        'select_check_out'  => 'Now select your check-out date',
        'amenities_label'   => 'Amenities',
        'capacity_label'    => 'Up to :count guests',
        'stay_range'        => ':min–:max nights',
        'error_resource_not_found'    => 'Room not found.',
        'error_invalid_date_range'    => 'Check-out must be after check-in.',
        'error_min_stay_violation'    => 'Minimum stay not met.',
        'error_max_stay_violation'    => 'Maximum stay exceeded.',
        'error_capacity_exceeded'     => 'Too many guests for this room.',
        'error_too_soon'              => 'Check-in date is too soon.',
        'error_too_far'               => 'Check-in date is too far ahead.',
        'error_date_blocked'          => 'One or more dates are blocked.',
        'error_already_booked'        => 'This room is already booked for those dates.',
    ],

    // ── Empty States ──
    'empty' => [
        'no_services'       => 'No services available',
        'no_services_desc'  => 'This business has not configured any services yet.',
        'no_availability'   => 'No available times this month.',
        'no_times'          => 'No available times on this day.',
        'coming_soon'       => 'Coming soon',
        'coming_soon_desc'  => 'This booking pattern is not yet available.',
        '404_title'         => 'Page not found',
        '404_desc'          => 'This booking page doesn\'t exist or is no longer active.',
        '404_help'          => 'If you followed a link here, please contact the business directly.',
        // Resource-pattern
        'no_resources'      => 'No rooms available',
        'no_resources_desc' => 'This business has not configured any rooms yet.',
        'no_dates'          => 'No available dates this month.',
    ],

    // ── Errors & Toasts ──
    'errors' => [
        'slot_taken'        => 'This time slot was just taken. Please choose another.',
        'generic'           => 'Something went wrong. Please try again.',
        'connection'        => 'A connection error occurred. Please try again.',
        'spam_detected'     => 'Your request could not be processed. Please try again.',
        'required_name'     => 'Please enter your name.',
        'required_email'    => 'Please enter a valid email address.',
    ],

    // ── Common / UI ──
    'common' => [
        'dismiss' => 'Dismiss',
    ],

    // ── Timezone ──
    'timezone' => [
        'label'            => 'Timezone',
        'same_as_business' => 'same as business',
        'notice'           => 'Times shown in your timezone (:tz)',
        'search'           => 'Search timezone…',
        'group_americas'   => 'Americas',
        'group_europe'     => 'Europe',
        'group_asia'       => 'Asia & Pacific',
        'group_africa'     => 'Africa',
    ],

    // ── Duration Formatting ──
    'duration' => [
        'hours'        => 'h',
        'minutes'      => 'min',
        'hours_long'   => ':h h :m min',
        'minutes_only' => ':m min',
    ],

    // ── API Error Messages (server-side, returned as JSON) ──
    'api' => [
        'invalid_date'        => 'Date parameter required (YYYY-MM-DD)',
        'name_email_required' => 'Name and email are required.',
        'invalid_email'       => 'Invalid email address.',
        'phone_required'      => 'Phone number is required.',
        'start_time_required' => 'Start time is required.',
        'slot_unavailable'    => 'This time slot was just taken.',
        'booking_failed'      => 'An error occurred while creating your booking. Please try again.',
        'invalid_json'        => 'Invalid request body.',
        'spam_detected'       => 'Invalid request.',
        'spam_retry'          => 'Please try again.',
        'max_bookings_exceeded' => 'You have reached the maximum number of bookings for this day.',
        'csrf_mismatch'       => 'Invalid security token. Please refresh and try again.',
        // Resource-pattern
        'resource_required'     => 'Please select a resource.',
        'check_in_required'     => 'Check-in date is required.',
        'check_out_required'    => 'Check-out date is required.',
        'guest_count_invalid'   => 'Guest count must be at least 1.',
        'resource_unavailable'  => 'This resource is not available for the selected dates.',
    ],

    // ── Recovery ──
    'recovery' => [
        'slot_taken' => 'That time was just booked. Try one of these instead:',
    ],

    // ── Calendar ──
    'calendar' => [
        'today'      => 'Today',
        'label'      => 'Calendar',
        'prev_month' => 'Previous month',
        'next_month' => 'Next month',
    ],

    // ── Day Names (0=Sunday) ──
    'days' => [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ],

    // ── Short Day Names ──
    'days_short' => [
        0 => 'SU',
        1 => 'MO',
        2 => 'TU',
        3 => 'WE',
        4 => 'TH',
        5 => 'FR',
        6 => 'SA',
    ],

    // ── Month Names (1-12) ──
    'months' => [
        1  => 'January',
        2  => 'February',
        3  => 'March',
        4  => 'April',
        5  => 'May',
        6  => 'June',
        7  => 'July',
        8  => 'August',
        9  => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ],

    // ── Footer ──
    'footer' => [
        'powered_by' => 'Powered by',
    ],

    // ── Privacy Pages ──
    'privacy' => [
        'page_title'              => 'Your Data',
        'meta_description'        => 'Review your data held by :business',
        'not_found'               => 'Page not found',
        'export_failed'           => 'Export failed. Please try again later.',
        'subtitle'                => 'Your data held by this business',
        'personal_info'           => 'Personal Information',
        'name_label'              => 'Name',
        'email_label'             => 'Email',
        'phone_label'             => 'Phone',
        'customer_since'          => 'Customer since',
        'booking_history'         => 'Booking History',
        'no_bookings'             => 'No bookings found.',
        'party_size'              => 'Party size',
        'source'                  => 'Source',
        'consent_records'         => 'Consent Records',
        'consented'               => 'Consented',
        'actions_title'           => 'Actions',
        'gdpr_rights'             => 'Under GDPR, you have the right to export your data or request its deletion.',
        'export_data'             => 'Export Data (JSON)',
        'request_deletion'        => 'Request Deletion',
        'confirm_warning_title'   => 'Are you sure?',
        'confirm_warning_body'    => 'This will request permanent removal of your personal data. This cannot be undone once processed by the business.',
        'cancel'                  => 'Cancel',
        'confirm_deletion'        => 'Confirm Deletion',
        'footer_server'           => 'Your data is stored on :business\'s server',
        'anonymized_page_title'   => 'Data Removed',
        'anonymized_title'        => 'Your Data Has Been Removed',
        'anonymized_message'      => 'Your personal information has been anonymized as requested. Booking records are retained for operational purposes, but your name, email, phone number, and personal notes have been permanently removed.',
        'deletion_req_page_title' => 'Deletion Requested',
        'deletion_req_title'      => 'Deletion Requested',
        'deletion_req_message'    => 'Your data deletion request has been logged. The business operating this service has been notified and will process your request.',
        'deletion_req_next_title' => 'What happens next:',
        'deletion_req_next_body'  => 'The business will review your request and remove your personal data. Under GDPR, they must respond within 30 days. Booking records may be retained in anonymized form for operational history, but all personal identifiers will be removed.',
    ],

    // ── Demo Mode ──
    'demo_notice' => 'This is a demo — bookings cannot be submitted.',
];
