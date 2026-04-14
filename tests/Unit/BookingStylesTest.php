<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BookingStylesTest extends TestCase
{
    public function test_resource_range_dot_uses_stronger_opacity(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/resources/css/booking.css');

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\\.vb-book-calendar-cell\\.is-range::after\\s*\\{[^}]*opacity:\\s*0\\.70;/s',
            $css
        );
    }
}
