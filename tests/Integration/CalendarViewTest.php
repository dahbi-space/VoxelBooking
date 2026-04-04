<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the tenant calendar views.
 *
 * Covers:
 * - Month view is the default entry surface (/calendar → month)
 * - Month view renders the grid with correct structure
 * - Day view ALWAYS renders the full timeline (never empty-state card)
 * - Day view separates action bar from navigation
 * - Week view is accessible at /calendar/week
 * - View switcher links use the correct URLs
 * - Day cells in month view link to /calendar/day
 * - Booking pills render when bookings exist
 * - Unauthenticated access redirects to login
 */
final class CalendarViewTest extends TestCase
{
    private static bool $appReachable = false;
    private string $baseUrl;
    private string $cookieJar;

    public static function setUpBeforeClass(): void
    {
        $baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');

        $ch = curl_init($baseUrl . '/health');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 5]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 0) {
            return;
        }

        self::$appReachable = true;

        try {
            TestFixtures::provision();
        } catch (\Throwable) {
            // best-effort
        }
    }

    protected function setUp(): void
    {
        if (!self::$appReachable) {
            $this->markTestSkipped('App not reachable');
        }

        $this->baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'vb_cal_test_') ?: '/tmp/vb_cal_test_cookies';
    }

    protected function tearDown(): void
    {
        if (isset($this->cookieJar) && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Month is the default entry surface
    // ════════════════════════════════════════════════════════════════

    public function test_calendar_root_renders_month_view(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            'vb-calendar-month',
            $r['body'],
            '/calendar should render the month grid (default surface)'
        );
        $this->assertStringContainsString(
            'vb-calendar-month-grid',
            $r['body'],
            'Month grid container should be present'
        );
    }

    public function test_month_view_renders_day_name_headers(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            'vb-calendar-month-dayname',
            $r['body'],
            'Month view should include day name headers'
        );
        $this->assertStringContainsString(
            'vb-calendar-month-header',
            $r['body'],
            'Month header row should be present'
        );
    }

    public function test_month_view_renders_42_cells(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        // 6 rows × 7 columns = 42 cells
        // Count only <a> tags with the cell class (not headers or other elements)
        preg_match_all('/<a\s[^>]*class="vb-calendar-month-cell[^"]*"/', $r['body'], $matches);
        $this->assertSame(42, count($matches[0]), 'Month grid should have exactly 42 cells (6 rows × 7 days)');
    }

    public function test_month_view_marks_today(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            'is-today',
            $r['body'],
            'Current month should mark today\'s cell'
        );
    }

    public function test_month_view_has_outside_month_cells(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            'is-outside',
            $r['body'],
            'Month grid should have outside-month cells for padding'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // View switcher uses .vb-tabs (not .vb-segmented)
    // ════════════════════════════════════════════════════════════════

    public function test_month_view_uses_tabs_not_segmented(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            'vb-tabs',
            $r['body'],
            'View switcher should use .vb-tabs premium tab system'
        );
        $this->assertStringNotContainsString(
            'vb-segmented',
            $r['body'],
            'View switcher should NOT use the old .vb-segmented component'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Day view accessible at /calendar/day
    // ════════════════════════════════════════════════════════════════

    public function test_day_view_always_renders_timeline(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        // Use a date with no bookings to prove the timeline still renders
        $emptyDate = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
        $r = $this->get("/admin/tenants/{$tid}/calendar/day?date={$emptyDate}");

        $this->assertSame(200, $r['code']);
        // Timeline MUST always be present — never an empty-state card
        $this->assertStringContainsString(
            'vb-calendar-timeline',
            $r['body'],
            'Day view must ALWAYS render the full timeline with clickable hour rails, even with zero bookings'
        );
        $this->assertStringNotContainsString(
            'vb-empty-state',
            $r['body'],
            'Day view must NOT use the empty-state card pattern — the timeline IS the empty state'
        );
        // Hour rails must be clickable links to create booking
        $this->assertStringContainsString(
            'vb-calendar-hour-link',
            $r['body'],
            'Hour rails must be clickable links for booking creation'
        );
    }

    public function test_day_view_has_new_booking_cta(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar/day");

        $this->assertSame(200, $r['code']);
        // Calendar header with CTA and tabs
        $this->assertStringContainsString(
            'vb-calendar-header',
            $r['body'],
            'Day view must have a calendar header'
        );
        $this->assertStringContainsString(
            'btn-calendar-new-booking',
            $r['body'],
            'New Booking button must exist in the header'
        );
    }

    public function test_day_view_uses_tabs_not_segmented(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar/day");

        $this->assertSame(200, $r['code']);
        $this->assertStringNotContainsString(
            'vb-segmented',
            $r['body'],
            'Day view should NOT use the old .vb-segmented component'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Week view accessible at /calendar/week
    // ════════════════════════════════════════════════════════════════

    public function test_week_view_accessible_at_calendar_week(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar/week");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            'vb-week',
            $r['body'],
            '/calendar/week should render the week schedule'
        );
    }

    public function test_week_view_uses_tabs_not_segmented(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar/week");

        $this->assertSame(200, $r['code']);
        $this->assertStringNotContainsString(
            'vb-segmented',
            $r['body'],
            'Week view should NOT use the old .vb-segmented component'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Day cells link to /calendar/day (not /calendar)
    // ════════════════════════════════════════════════════════════════

    public function test_month_day_cells_link_to_day_view(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            '/calendar/day?date=',
            $r['body'],
            'Month cells should link to /calendar/day?date=...'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Calendar navigation
    // ════════════════════════════════════════════════════════════════

    public function test_month_view_has_navigation_controls(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        $this->assertStringContainsString(
            'vb-calendar-nav',
            $r['body'],
            'Calendar navigation should be present'
        );
        $this->assertStringContainsString(
            'vb-calendar-date-title',
            $r['body'],
            'Date title (month name + year) should be visible'
        );
    }

    public function test_month_view_date_navigation_stays_on_month(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(200, $r['code']);
        // Prev/next month links should stay within /calendar?date=...
        $this->assertMatchesRegularExpression(
            '#/calendar\?date=\d{4}-\d{2}-\d{2}#',
            $r['body'],
            'Month navigation arrows should link to /calendar?date=...'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Booking pills & overflow
    // ════════════════════════════════════════════════════════════════

    public function test_month_view_renders_booking_pills_when_bookings_exist(): void
    {
        $this->doLoginOperator();
        $tid = TestFixtures::BUSINESS_TENANT_ID;

        // The fixture booking is tomorrow — navigate to the correct month
        $tomorrow = (new \DateTimeImmutable('+1 day'))->format('Y-m-d');
        $r = $this->get("/admin/tenants/{$tid}/calendar?date={$tomorrow}");

        $this->assertSame(200, $r['code']);
        // The test fixture creates a booking for tomorrow
        $this->assertStringContainsString(
            'vb-calendar-month-pill',
            $r['body'],
            'Month view should render booking pills when bookings exist'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // Auth guard
    // ════════════════════════════════════════════════════════════════

    public function test_unauthenticated_calendar_redirects_to_login(): void
    {
        // Fresh cookie jar — no session
        $tid = TestFixtures::BUSINESS_TENANT_ID;
        $r = $this->get("/admin/tenants/{$tid}/calendar");

        $this->assertSame(302, $r['code']);
        $this->assertStringContainsString('/admin/login', $r['location']);
    }

    // ── Helpers ──

    private function doLoginOperator(): void
    {
        $r = $this->get('/admin/login');
        preg_match('/name="_csrf_token" value="([^"]+)"/', $r['body'], $m);
        $csrf = $m[1] ?? '';

        $this->post('/admin/login', [
            'email' => TestFixtures::OPERATOR_EMAIL,
            'password' => TestFixtures::OPERATOR_PASSWORD,
            '_csrf_token' => $csrf,
        ]);
    }

    /**
     * @return array{code: int, body: string, location: string}
     */
    private function get(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ]);
        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $body = substr($response, $headerSize);
        $headers = substr($response, 0, $headerSize);
        $location = '';
        if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
            $location = trim($m[1]);
        }

        return compact('code', 'body', 'location');
    }

    private function post(string $path, array $data): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ]);
        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $body = substr($response, $headerSize);
        $headers = substr($response, 0, $headerSize);
        $location = '';
        if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
            $location = trim($m[1]);
        }

        return compact('code', 'body', 'location');
    }
}
