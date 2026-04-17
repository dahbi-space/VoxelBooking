<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Validates that the JS reschedule payload construction is correct
 * by inspecting the app.js source for each pattern branch.
 *
 * This catches the P1 class of bugs where the payload is missing
 * required fields that the backend resolvers expect.
 */
final class ReschedulePayloadContractTest extends TestCase
{
    private string $jsSource;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/js/booking/app.js';
        $this->jsSource = (string) file_get_contents($path);
        $this->assertNotEmpty($this->jsSource, 'app.js source should be readable');
    }

    /**
     * Extract the _buildReschedulePayload method body.
     */
    private function getPayloadMethodBody(): string
    {
        preg_match('/_buildReschedulePayload\(\)\s*\{(.*?)\n    \},/s', $this->jsSource, $m);
        return $m[1] ?? '';
    }

    /**
     * Timeslot payload must include new_date and new_time.
     */
    public function test_timeslot_payload_includes_date_and_time(): void
    {
        $body = $this->getPayloadMethodBody();
        $this->assertNotEmpty($body, '_buildReschedulePayload should exist');
        $this->assertStringContainsString('new_date:', $body);
        $this->assertStringContainsString('new_time:', $body);
    }

    /**
     * Resource payload must include check_in and check_out.
     */
    public function test_resource_payload_includes_check_in_out(): void
    {
        $body = $this->getPayloadMethodBody();
        $this->assertStringContainsString('check_in:', $body);
        $this->assertStringContainsString('check_out:', $body);
    }

    /**
     * Capacity payload must include date and slot_id.
     */
    public function test_capacity_payload_includes_date_and_slot_id(): void
    {
        $body = $this->getPayloadMethodBody();
        $this->assertStringContainsString('date:', $body);
        $this->assertStringContainsString('slot_id:', $body);
    }

    /**
     * Event payload must include BOTH date and event_id.
     * This is the P1 regression guard — the backend's resolveEventTarget
     * requires date and throws invalid_date when it's missing.
     */
    public function test_event_payload_includes_date_and_event_id(): void
    {
        $body = $this->getPayloadMethodBody();

        // Extract the event branch specifically
        preg_match("/if\s*\(pattern\s*===\s*'event'\)\s*\{[^}]*return\s*\{([^}]+)\}/s", $body, $evMatch);
        $eventReturn = $evMatch[1] ?? '';
        $this->assertNotEmpty($eventReturn, 'Event branch should exist in payload builder');

        $this->assertStringContainsString('date:', $eventReturn,
            'Event payload must include date (required by resolveEventTarget)');
        $this->assertStringContainsString('event_id:', $eventReturn,
            'Event payload must include event_id');
    }

    /**
     * Event filter must NOT exclude all occurrences of the same event_id.
     * It should only exclude the exact same occurrence (same id + same date).
     */
    public function test_event_filter_allows_same_event_different_date(): void
    {
        // Extract loadRescheduleEvents method
        preg_match('/loadRescheduleEvents\(\)\s*\{(.*?)\n    \},/s', $this->jsSource, $m);
        $body = $m[1] ?? '';
        $this->assertNotEmpty($body, 'loadRescheduleEvents should exist');

        // The filter must compare BOTH event_id AND date, not just event_id
        $this->assertMatchesRegularExpression(
            '/ev\.id\s*===\s*currentEventId\s*&&\s*ev\.date\s*===\s*currentDate/',
            $body,
            'Event filter must exclude by event_id AND date, not event_id alone'
        );
    }
}
