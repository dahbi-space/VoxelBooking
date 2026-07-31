<?php

declare(strict_types=1);

namespace Tests\Unit\Perka\WhatsAppAutomation;

use App\Perka\Modules\WhatsAppAutomation\Services\WhatsAppAutomationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WhatsAppAutomationService's DB-free logic.
 *
 * The instance-resolution short-circuit for an empty/blank instance is verified
 * here without a database — an empty instance can never match a row, so the
 * service must return null before issuing any query. DB-backed lookups are
 * covered by integration tests against a live database.
 */
final class WhatsAppAutomationServiceTest extends TestCase
{
    public function testEmptyInstanceReturnsNullWithoutQuerying(): void
    {
        $service = new WhatsAppAutomationService();

        $this->assertNull($service->getByInstance(''),
            'A blank instance must resolve to null (uniform 404) without touching the DB.');
    }
}
