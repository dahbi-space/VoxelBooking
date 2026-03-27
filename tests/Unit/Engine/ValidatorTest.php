<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredRule(): void
    {
        $errors = Validator::validate(
            ['name' => ''],
            ['name' => 'required']
        );

        $this->assertArrayHasKey('name', $errors);
        $this->assertStringContainsString('required', $errors['name']);
    }

    public function testRequiredPassesWithValue(): void
    {
        $errors = Validator::validate(
            ['name' => 'Alice'],
            ['name' => 'required']
        );

        $this->assertEmpty($errors);
    }

    public function testEmailRule(): void
    {
        $errors = Validator::validate(
            ['email' => 'not-an-email'],
            ['email' => 'required|email']
        );

        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidEmail(): void
    {
        $errors = Validator::validate(
            ['email' => 'test@example.com'],
            ['email' => 'required|email']
        );

        $this->assertEmpty($errors);
    }

    public function testMinRule(): void
    {
        $errors = Validator::validate(
            ['password' => 'short'],
            ['password' => 'required|min:8']
        );

        $this->assertArrayHasKey('password', $errors);
    }

    public function testMaxLengthRule(): void
    {
        $errors = Validator::validate(
            ['name' => str_repeat('a', 256)],
            ['name' => 'required|max_length:255']
        );

        $this->assertArrayHasKey('name', $errors);
    }

    public function testInRule(): void
    {
        $errors = Validator::validate(
            ['pattern' => 'invalid'],
            ['pattern' => 'required|in:timeslot,resource,capacity,event']
        );

        $this->assertArrayHasKey('pattern', $errors);
    }

    public function testInRulePasses(): void
    {
        $errors = Validator::validate(
            ['pattern' => 'timeslot'],
            ['pattern' => 'required|in:timeslot,resource,capacity,event']
        );

        $this->assertEmpty($errors);
    }

    public function testMultipleFieldValidation(): void
    {
        $errors = Validator::validate(
            ['name' => '', 'email' => 'bad'],
            ['name' => 'required', 'email' => 'required|email']
        );

        $this->assertCount(2, $errors);
    }

    public function testMissingFieldTreatedAsEmpty(): void
    {
        $errors = Validator::validate(
            [],
            ['name' => 'required']
        );

        $this->assertArrayHasKey('name', $errors);
    }
}
