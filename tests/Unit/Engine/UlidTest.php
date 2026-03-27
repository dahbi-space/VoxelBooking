<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\Ulid;
use PHPUnit\Framework\TestCase;

final class UlidTest extends TestCase
{
    public function testGeneratesValidUlid(): void
    {
        $ulid = Ulid::generate();

        $this->assertSame(26, strlen($ulid));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid);
    }

    public function testGeneratesUniqueIds(): void
    {
        $a = Ulid::generate();
        $b = Ulid::generate();

        $this->assertNotSame($a, $b);
    }

    public function testIsMonotonicallyIncreasing(): void
    {
        $a = Ulid::generate();
        usleep(1000); // 1ms
        $b = Ulid::generate();

        $this->assertGreaterThan($a, $b);
    }
}
