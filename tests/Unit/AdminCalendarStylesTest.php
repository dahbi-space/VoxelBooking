<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminCalendarStylesTest extends TestCase
{
    public function test_month_today_badge_stays_white_when_day_is_closed(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/resources/css/admin.css');

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\\.vb-calendar-month-cell\\.is-unavailable\\s+\\.vb-calendar-month-day\\.is-today(?:\\s*,\\s*\\.vb-calendar-month-cell\\.is-blocked\\s+\\.vb-calendar-month-day\\.is-today)?\\s*\\{[^}]*color:\\s*#fff;/s',
            $css
        );
    }
}
