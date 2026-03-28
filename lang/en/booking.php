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
        'pick_another_time'=> 'Pick another time',
    ],

    // ── Confirmation ──
    'confirmed' => [
        'heading'           => 'Booking confirmed',
        'message'           => 'A confirmation has been sent to :email.',
        'reference_label'   => 'Reference',
    ],

    // ── Summary ──
    'summary' => [
        'service_label'  => 'Service',
        'with_label'     => 'With',
        'date_label'     => 'Date',
        'time_label'     => 'Time',
        'duration_label' => 'Duration',
        'price_label'    => 'Price',
    ],

    // ── Empty States ──
    'empty' => [
        'no_services'       => 'No services available',
        'no_services_desc'  => 'This business has not configured any services yet.',
        'no_availability'   => 'No available times this month.',
        'coming_soon'       => 'Coming soon',
        'coming_soon_desc'  => 'This booking pattern is not yet available.',
        '404_title'         => 'Page not found',
        '404_desc'          => 'This booking page doesn\'t exist or is no longer active.',
        '404_help'          => 'If you followed a link here, please contact the business directly.',
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
    ],

    // ── Calendar ──
    'calendar' => [
        'today' => 'Today',
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
];
