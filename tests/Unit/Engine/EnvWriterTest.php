<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\EnvWriter;
use PHPUnit\Framework\TestCase;

final class EnvWriterTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/vb_test_env_writer_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testWritesNewKeyToEmptyFile(): void
    {
        file_put_contents($this->tempFile, '');

        EnvWriter::setMultiple($this->tempFile, ['DB_HOST' => '127.0.0.1']);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $content);
    }

    public function testUpdatesExistingKey(): void
    {
        file_put_contents($this->tempFile, "DB_HOST=localhost\nDB_PORT=3306\n");

        EnvWriter::setMultiple($this->tempFile, ['DB_HOST' => '192.168.1.1']);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('DB_HOST=192.168.1.1', $content);
        $this->assertStringNotContainsString('DB_HOST=localhost', $content);
        $this->assertStringContainsString('DB_PORT=3306', $content);
    }

    public function testPreservesComments(): void
    {
        file_put_contents($this->tempFile, "# Database\nDB_HOST=localhost\n");

        EnvWriter::setMultiple($this->tempFile, ['DB_HOST' => '10.0.0.1']);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('# Database', $content);
    }

    public function testWritesMultipleKeys(): void
    {
        file_put_contents($this->tempFile, '');

        EnvWriter::setMultiple($this->tempFile, [
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3309',
            'DB_DATABASE' => 'voxelbooking',
        ]);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $content);
        $this->assertStringContainsString('DB_PORT=3309', $content);
        $this->assertStringContainsString('DB_DATABASE=voxelbooking', $content);
    }

    public function testQuotesValuesWithSpaces(): void
    {
        file_put_contents($this->tempFile, '');

        EnvWriter::setMultiple($this->tempFile, ['APP_NAME' => 'My Booking App']);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('APP_NAME="My Booking App"', $content);
    }

    public function testCreatesFileIfMissing(): void
    {
        @unlink($this->tempFile);
        $this->assertFileDoesNotExist($this->tempFile);

        EnvWriter::setMultiple($this->tempFile, ['DB_HOST' => 'localhost']);

        $this->assertFileExists($this->tempFile);
        $this->assertStringContainsString('DB_HOST=localhost', file_get_contents($this->tempFile));
    }
}
