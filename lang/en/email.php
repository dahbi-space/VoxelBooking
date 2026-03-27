<?php

declare(strict_types=1);

/**
 * English translations: email subjects and content.
 */
return [
    'booking_confirmation' => [
        'subject'  => 'Booking confirmed – :service on :date',
        'greeting' => 'Hi :name,',
        'body'     => 'Your booking has been confirmed.',
        'details'  => 'Booking details',
        'footer'   => 'If you need to make changes, please contact us.',
    ],

    'booking_reminder' => [
        'subject'  => 'Reminder: :service tomorrow at :time',
        'greeting' => 'Hi :name,',
        'body'     => 'This is a reminder for your upcoming appointment.',
    ],

    'operator_notification' => [
        'subject'   => 'New booking: :service – :customer',
        'body'      => 'A new booking has been made.',
    ],

    'privacy_acknowledgment' => [
        'subject'  => 'Your privacy request has been received',
        'greeting' => 'Hi :name,',
        'body'     => 'We have received your data request and will process it within 30 days.',
    ],

    'deletion_completed' => [
        'subject' => 'Your data has been deleted',
        'body'    => 'Your personal data has been removed from our systems.',
    ],

    'common' => [
        'regards'    => 'Best regards,',
        'powered_by' => 'Powered by VoxelBooking',
    ],
];
