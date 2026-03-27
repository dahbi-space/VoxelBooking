<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\EnvLoader;
use PHPUnit\Framework\TestCase;

final class EnvLoaderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/vb_test_env_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }

        // Clean up loaded env vars
        foreach (['TEST_KEY', 'TEST_QUOTED', 'TEST_SPACED', 'TEST_EMPTY'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function testLoadsSimpleKeyValue(): void
    {
        file_put_contents($this->tempFile, "TEST_KEY=hello\n");

        EnvLoader::load($this->tempFile);

        $this->assertSame('hello', $_ENV['TEST_KEY']);
    }

    public function testLoadsQuotedValue(): void
    {
        file_put_contents($this->tempFile, "TEST_QUOTED=\"hello world\"\n");

        EnvLoader::load($this->tempFile);

        $this->assertSame('hello world', $_ENV['TEST_QUOTED']);
    }

    public function testSkipsComments(): void
    {
        file_put_contents($this->tempFile, "# This is a comment\nTEST_KEY=value\n");

        EnvLoader::load($this->tempFile);

        $this->assertSame('value', $_ENV['TEST_KEY']);
        $this->assertArrayNotHasKey('# This is a comment', $_ENV);
    }

    public function testSkipsEmptyLines(): void
    {
        file_put_contents($this->tempFile, "\n\nTEST_KEY=value\n\n");

        EnvLoader::load($this->tempFile);

        $this->assertSame('value', $_ENV['TEST_KEY']);
    }

    public function testDoesNotOverwriteExisting(): void
    {
        $_ENV['TEST_KEY'] = 'original';
        file_put_contents($this->tempFile, "TEST_KEY=overwritten\n");

        EnvLoader::load($this->tempFile);

        $this->assertSame('original', $_ENV['TEST_KEY']);
    }

    public function testHandlesMissingFile(): void
    {
        // Should not throw
        EnvLoader::load('/nonexistent/path/.env');

        $this->assertTrue(true);
    }

    public function testHandlesEmptyValue(): void
    {
        file_put_contents($this->tempFile, "TEST_EMPTY=\n");

        EnvLoader::load($this->tempFile);

        $this->assertSame('', $_ENV['TEST_EMPTY']);
    }
}
