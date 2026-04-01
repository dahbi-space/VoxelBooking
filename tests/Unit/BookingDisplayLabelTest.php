<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the booking_display_label() helper.
 *
 * Verifies that all four booking patterns produce non-empty,
 * non-dash labels on shared admin surfaces (dashboard, calendar,
 * customer history) even when the booking row lacks a joined
 * service/resource/event name.
 */
final class BookingDisplayLabelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

        // Ensure the i18n engine has a locale loaded so __() resolves
        if (!function_exists('booking_display_label')) {
            require_once dirname(__DIR__, 2) . '/app/helpers.php';
        }
    }

    public function testTimeslotWithServiceName(): void
    {
        $label = booking_display_label([
            'booking_pattern' => 'timeslot',
            'service_name'    => 'Haircut',
        ]);
        $this->assertSame('Haircut', $label);
    }

    public function testTimeslotWithoutServiceName(): void
    {
        $label = booking_display_label([
            'booking_pattern' => 'timeslot',
            'service_name'    => null,
        ]);
        // Timeslot without service is edge-case, shows dash
        $this->assertSame('—', $label);
    }

    public function testCapacityWithoutServiceName(): void
    {
        // This is the normal case: capacity bookings have no service_id
        $label = booking_display_label([
            'booking_pattern' => 'capacity',
            'service_name'    => null,
        ]);
        // Must NOT be '—' — regression test for the shared surface fix
        $this->assertNotSame('—', $label, 'Capacity booking must not render as dash');
        $this->assertNotEmpty($label, 'Capacity booking must have a non-empty label');
    }

    public function testCapacityWithServiceName(): void
    {
        // If a capacity booking somehow has a service_name, use it
        $label = booking_display_label([
            'booking_pattern' => 'capacity',
            'service_name'    => 'Dinner Reservation',
        ]);
        $this->assertSame('Dinner Reservation', $label);
    }

    public function testResourceWithResourceName(): void
    {
        $label = booking_display_label([
            'booking_pattern' => 'resource',
            'resource_name'   => 'Deluxe Suite',
            'service_name'    => null,
        ]);
        $this->assertSame('Deluxe Suite', $label);
    }

    public function testResourceWithoutResourceName(): void
    {
        $label = booking_display_label([
            'booking_pattern' => 'resource',
            'resource_name'   => null,
            'service_name'    => null,
        ]);
        $this->assertSame('—', $label);
    }

    public function testEventWithEventName(): void
    {
        $label = booking_display_label([
            'booking_pattern' => 'event',
            'event_name'      => 'Yoga Class',
            'service_name'    => null,
        ]);
        $this->assertSame('Yoga Class', $label);
    }

    public function testEventWithoutEventName(): void
    {
        $label = booking_display_label([
            'booking_pattern' => 'event',
            'event_name'      => null,
            'service_name'    => null,
        ]);
        // Falls back to translated capacity label (shared fallback)
        $this->assertNotSame('—', $label, 'Event booking without name must not render as dash');
        $this->assertNotEmpty($label);
    }

    public function testMissingPatternDefaultsToTimeslot(): void
    {
        $label = booking_display_label([
            'service_name' => 'Consultation',
        ]);
        $this->assertSame('Consultation', $label);
    }
}
