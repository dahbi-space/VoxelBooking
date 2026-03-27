<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\RetentionJob;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RetentionJob.
 *
 * Verifies the retention job orchestration structure:
 * - Customer anonymization per tenant
 * - Audit log cleanup
 * - Email log cleanup
 * - Rate limit cleanup
 *
 * Method signature tests — full integration requires database.
 */
final class RetentionJobTest extends TestCase
{
    public function testRunMethodExists(): void
    {
        $this->assertTrue(
            method_exists(RetentionJob::class, 'run'),
            'RetentionJob must have a run() method'
        );

        $ref = new \ReflectionMethod(RetentionJob::class, 'run');
        $this->assertTrue($ref->isStatic(), 'RetentionJob::run() must be static');
        $this->assertTrue($ref->isPublic(), 'RetentionJob::run() must be public');
    }

    public function testReturnStructureDocumented(): void
    {
        $ref = new \ReflectionMethod(RetentionJob::class, 'run');
        $docComment = $ref->getDocComment();

        $this->assertStringContainsString('anonymization', $docComment);
        $this->assertStringContainsString('audit_cleanup', $docComment);
        $this->assertStringContainsString('email_cleanup', $docComment);
        $this->assertStringContainsString('rate_limit_cleanup', $docComment);
        $this->assertStringContainsString('errors', $docComment);
    }

    public function testJobProcessesAreIndependent(): void
    {
        // Verify the four processing methods exist as private static
        $processNames = [
            'processCustomerRetention',
            'processAuditCleanup',
            'processEmailLogCleanup',
            'processRateLimitCleanup',
        ];

        foreach ($processNames as $method) {
            $this->assertTrue(
                method_exists(RetentionJob::class, $method),
                "RetentionJob must have {$method}() method"
            );

            $ref = new \ReflectionMethod(RetentionJob::class, $method);
            $this->assertTrue($ref->isPrivate(), "{$method}() must be private");
            $this->assertTrue($ref->isStatic(), "{$method}() must be static");
        }
    }
}
