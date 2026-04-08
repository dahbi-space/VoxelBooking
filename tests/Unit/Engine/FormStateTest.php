<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\FormState;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the FormState engine.
 *
 * Tests the unified form state API that replaces ad-hoc
 * $_SESSION handling across 14 admin controllers.
 *
 * The engine uses consume-on-first-access semantics: the first call
 * to old() / errors() consumes from $_SESSION into a static cache.
 * Within the same request, subsequent reads come from the cache.
 * Across requests (i.e. after redirect), the session data is gone
 * so the form renders clean.
 */
class FormStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure session is available (PHPUnit runs in CLI)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // Clear any leftover state (session + static caches)
        FormState::clear();
    }

    protected function tearDown(): void
    {
        FormState::clear();
        parent::tearDown();
    }

    // ── flashInput / old ──

    public function testFlashInputAndRetrieve(): void
    {
        FormState::flashInput(['name' => 'John', 'email' => 'john@example.com']);

        $old = FormState::old();
        $this->assertSame('John', $old['name']);
        $this->assertSame('john@example.com', $old['email']);
    }

    public function testOldIsIdempotentWithinRequest(): void
    {
        FormState::flashInput(['name' => 'John']);

        $first = FormState::old();
        $this->assertSame('John', $first['name']);

        // Idempotent within request — cached data still available
        $second = FormState::old();
        $this->assertSame('John', $second['name']);
    }

    public function testOldIsOneShotAcrossRequests(): void
    {
        FormState::flashInput(['name' => 'John']);

        // First "request" — consumes from session
        $first = FormState::old();
        $this->assertSame('John', $first['name']);

        // Simulate new request by clearing caches (not session, which is already gone)
        FormState::clear();

        // Second "request" — session data was consumed, nothing left
        $second = FormState::old();
        $this->assertEmpty($second);
    }

    public function testOldReturnsEmptyArrayWhenNothingFlashed(): void
    {
        $this->assertSame([], FormState::old());
    }

    public function testOldValueWithFallback(): void
    {
        FormState::flashInput(['name' => 'John']);

        $this->assertSame('John', FormState::oldValue('name', 'default'));
        $this->assertSame('default', FormState::oldValue('nonexistent', 'default'));
    }

    public function testOldValueMultipleCallsSafe(): void
    {
        FormState::flashInput(['name' => 'John']);

        // Multiple reads of the same key — all return correct value
        $this->assertSame('John', FormState::oldValue('name'));
        $this->assertSame('John', FormState::oldValue('name'));
        $this->assertSame('John', FormState::oldValue('name'));
    }

    // ── flashErrors / errors ──

    public function testFlashErrorsAndRetrieve(): void
    {
        FormState::flashErrors(['email' => 'Email is required', 'name' => 'Name is required']);

        $errors = FormState::errors();
        $this->assertSame('Email is required', $errors['email']);
        $this->assertSame('Name is required', $errors['name']);
    }

    public function testErrorsIsIdempotentWithinRequest(): void
    {
        FormState::flashErrors(['email' => 'Required']);

        $first = FormState::errors();
        $this->assertNotEmpty($first);

        $second = FormState::errors();
        $this->assertNotEmpty($second, 'Errors must be available for multiple reads within request');
    }

    public function testErrorsIsOneShotAcrossRequests(): void
    {
        FormState::flashErrors(['email' => 'Required']);

        $first = FormState::errors();
        $this->assertNotEmpty($first);

        // Simulate new request
        FormState::clear();

        $second = FormState::errors();
        $this->assertEmpty($second);
    }

    public function testHasError(): void
    {
        FormState::flashErrors(['email' => 'Invalid email']);

        $this->assertTrue(FormState::hasError('email'));
        $this->assertFalse(FormState::hasError('name'));
    }

    public function testFieldError(): void
    {
        FormState::flashErrors(['email' => 'Invalid email']);

        $this->assertSame('Invalid email', FormState::fieldError('email'));
        $this->assertSame('', FormState::fieldError('name'));
    }

    public function testHasErrorMultipleCallsSafe(): void
    {
        FormState::flashErrors(['email' => 'Invalid']);

        $this->assertTrue(FormState::hasError('email'));
        $this->assertTrue(FormState::hasError('email'));
        $this->assertSame('Invalid', FormState::fieldError('email'));
        $this->assertSame('Invalid', FormState::fieldError('email'));
    }

    // ── flash (combined) ──

    public function testFlashCombinesInputAndErrors(): void
    {
        FormState::flash(
            ['name' => 'John', 'email' => 'bad'],
            ['email' => 'Invalid email format']
        );

        $old = FormState::old();
        $this->assertSame('John', $old['name']);
        $this->assertSame('bad', $old['email']);

        $this->assertTrue(FormState::hasError('email'));
        $this->assertFalse(FormState::hasError('name'));

        $errors = FormState::errors();
        $this->assertSame('Invalid email format', $errors['email']);
    }

    public function testFlashWithEmptyErrorsDoesNotSetErrors(): void
    {
        FormState::flash(['name' => 'John']);

        $this->assertFalse(FormState::hasError('name'));
        $this->assertEmpty(FormState::errors());
    }

    // ── toast ──

    public function testToastAndRetrieve(): void
    {
        FormState::toast('success', 'Settings saved.');

        $toast = FormState::getToast();
        $this->assertSame('success', $toast['type']);
        $this->assertSame('Settings saved.', $toast['message']);
    }

    public function testToastIsOneShot(): void
    {
        FormState::toast('error', 'Validation failed.');

        $first = FormState::getToast();
        $this->assertNotNull($first);

        $second = FormState::getToast();
        $this->assertNull($second);
    }

    public function testGetToastReturnsNullWhenNothingFlashed(): void
    {
        $this->assertNull(FormState::getToast());
    }

    // ── clear ──

    public function testClearRemovesAllState(): void
    {
        FormState::flashInput(['name' => 'John']);
        FormState::flashErrors(['email' => 'Required']);
        FormState::toast('error', 'Failed');

        FormState::clear();

        $this->assertEmpty(FormState::old());
        $this->assertEmpty(FormState::errors());
        $this->assertNull(FormState::getToast());
    }

    // ── Edge cases ──

    public function testFlashInputOverwritesPreviousInput(): void
    {
        FormState::flashInput(['name' => 'First']);
        FormState::flashInput(['name' => 'Second']);

        $old = FormState::old();
        $this->assertSame('Second', $old['name']);
    }

    public function testFlashErrorsOverwritesPreviousErrors(): void
    {
        FormState::flashErrors(['email' => 'First error']);
        FormState::flashErrors(['email' => 'Second error']);

        $this->assertSame('Second error', FormState::fieldError('email'));
    }

    public function testToastOverwritesPreviousToast(): void
    {
        FormState::toast('success', 'First');
        FormState::toast('error', 'Second');

        $toast = FormState::getToast();
        $this->assertSame('error', $toast['type']);
        $this->assertSame('Second', $toast['message']);
    }

    public function testInputAndErrorsAreIndependent(): void
    {
        FormState::flashInput(['name' => 'John']);
        FormState::flashErrors(['email' => 'Required']);

        // Consuming old input doesn't affect errors
        $old = FormState::old();
        $this->assertSame('John', $old['name']);
        $this->assertTrue(FormState::hasError('email'));

        // Consuming errors doesn't affect input
        $errors = FormState::errors();
        $this->assertSame('Required', $errors['email']);

        // Both still available within same request
        $this->assertSame('John', FormState::oldValue('name'));
        $this->assertTrue(FormState::hasError('email'));
    }

    // ── Helper function tests ──

    public function testErrorClassReturnsIsInvalid(): void
    {
        FormState::flashErrors(['email' => 'Invalid email']);

        $this->assertSame(' is-invalid', error_class('email'));
        $this->assertSame('', error_class('name'));
    }

    public function testOldHelperReturnsFlashedValue(): void
    {
        FormState::flashInput(['name' => 'John']);

        $this->assertSame('John', old('name'));
        $this->assertSame('default', old('missing', 'default'));
    }

    public function testHasErrorHelper(): void
    {
        FormState::flashErrors(['email' => 'Required']);

        $this->assertTrue(has_error('email'));
        $this->assertFalse(has_error('name'));
    }

    public function testFieldErrorHelper(): void
    {
        FormState::flashErrors(['email' => 'Invalid format']);

        $this->assertSame('Invalid format', field_error('email'));
        $this->assertSame('', field_error('name'));
    }

    public function testClearOnSuccessPathPreventsStaleInput(): void
    {
        // Simulate: controller flashes input eagerly, then succeeds
        FormState::flashInput(['name' => 'John']);
        FormState::clear(); // success path clears
        FormState::toast('success', 'Created.');

        // Next GET: no stale old input, but toast is present
        $old = FormState::old();
        $this->assertEmpty($old, 'Old input must be cleared on success');

        $toast = FormState::getToast();
        $this->assertSame('success', $toast['type']);
    }
}
