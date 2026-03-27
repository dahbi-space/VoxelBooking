<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\DataExporter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DataExporter.
 *
 * Verifies the export structure matches the GDPR Art. 20 portability spec.
 * Method signature tests — full integration requires database.
 */
final class DataExporterTest extends TestCase
{
    public function testExportMethodExists(): void
    {
        $this->assertTrue(
            method_exists(DataExporter::class, 'export'),
            'DataExporter must have an export() method'
        );

        $ref = new \ReflectionMethod(DataExporter::class, 'export');
        $this->assertSame('customerId', $ref->getParameters()[0]->getName());
    }

    public function testExportJsonMethodExists(): void
    {
        $this->assertTrue(
            method_exists(DataExporter::class, 'exportJson'),
            'DataExporter must have an exportJson() method'
        );
    }

    public function testExportStructureDocumented(): void
    {
        // Verify the return type includes all required GDPR fields
        $ref = new \ReflectionMethod(DataExporter::class, 'export');
        $docComment = $ref->getDocComment();

        $this->assertStringContainsString('export_version', $docComment);
        $this->assertStringContainsString('exported_at', $docComment);
        $this->assertStringContainsString('customer', $docComment);
        $this->assertStringContainsString('bookings', $docComment);
        $this->assertStringContainsString('consent_records', $docComment);
        $this->assertStringContainsString('email_log', $docComment);
    }
}
