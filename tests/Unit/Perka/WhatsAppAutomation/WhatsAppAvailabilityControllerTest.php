<?php

declare(strict_types=1);

namespace Tests\Unit\Perka\WhatsAppAutomation;

use App\Engine\Request;
use App\Perka\Modules\WhatsAppAutomation\Controllers\Api\WhatsAppAvailabilityController;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the availability endpoint's date-validation contract.
 *
 * These paths short-circuit before any DB access — a missing or malformed
 * `date` is rejected with 400 before the service (and TimeSlotCalculator) is
 * ever reached. DB-backed lookups are covered by the round-trip integration
 * run against a live database.
 */
final class WhatsAppAvailabilityControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $getBackup = [];

    protected function setUp(): void
    {
        $this->getBackup = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        $_POST = [];
    }

    private function showWith(array $query): int
    {
        $_GET = $query;
        $_POST = [];
        $controller = new WhatsAppAvailabilityController();

        return $controller->show(new Request())->getStatusCode();
    }

    public function testMissingDateReturns400(): void
    {
        $this->assertSame(400, $this->showWith(['instance' => 'acme']));
    }

    public function testMalformedDateReturns400(): void
    {
        // Shape validation only (matches the public availability endpoint's
        // /^\d{4}-\d{2}-\d{2}$/ contract). A wrong-order or non-date string
        // fails the shape and 400s before any DB lookup. Note: a shape-valid
        // but calendar-invalid string like "2026-13-40" intentionally passes
        // shape validation here, exactly as it does on the public endpoint.
        $this->assertSame(400, $this->showWith(['instance' => 'acme', 'date' => '05-08-2026']));
        $this->assertSame(400, $this->showWith(['instance' => 'acme', 'date' => 'not-a-date']));
        $this->assertSame(400, $this->showWith(['instance' => 'acme', 'date' => '2026-8-5']));
    }

    public function testMalformedDateIsRejectedBeforeInstanceLookup(): void
    {
        // Even with an empty instance, a bad date must 400 (validation first),
        // proving no DB lookup is attempted on the invalid-date path.
        $this->assertSame(400, $this->showWith(['instance' => '', 'date' => 'nope']));
    }
}
