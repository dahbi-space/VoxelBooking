<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Output tests for the login page template.
 *
 * Renders templates/auth/login.php and asserts expected
 * HTML elements are present.
 */
final class LoginOutputTest extends TestCase
{
    private function renderLogin(array $vars = []): string
    {
        // Set up translation function if not loaded
        if (!function_exists('__')) {
            require_once dirname(__DIR__, 2) . '/app/Engine/Locale.php';
            \App\Engine\Locale::loadTranslations(dirname(__DIR__, 2) . '/lang');
        }

        if (!function_exists('app_name')) {
            require_once dirname(__DIR__, 2) . '/app/helpers.php';
        }

        $csrfToken = $vars['csrfToken'] ?? 'test-csrf-token';
        $error = $vars['error'] ?? null;
        $lastEmail = $vars['lastEmail'] ?? '';

        ob_start();
        include dirname(__DIR__, 2) . '/templates/auth/login.php';
        return ob_get_clean() ?: '';
    }

    public function test_login_page_renders_remember_me_checkbox(): void
    {
        $html = $this->renderLogin();

        $this->assertStringContainsString(
            'name="remember_me"',
            $html,
            'Login page should contain a remember_me checkbox'
        );
        $this->assertStringContainsString(
            'type="checkbox"',
            $html,
            'remember_me field should be a checkbox'
        );
    }

    public function test_login_page_renders_method_selector(): void
    {
        $html = $this->renderLogin();

        $this->assertStringContainsString(
            'login-method-tabs',
            $html,
            'Login page should contain method selector tabs'
        );
        $this->assertStringContainsString(
            'data-method="password"',
            $html,
            'Login page should have password tab'
        );
        $this->assertStringContainsString(
            'data-method="otp"',
            $html,
            'Login page should have OTP tab'
        );
        $this->assertStringContainsString(
            'data-method="magic_link"',
            $html,
            'Login page should have magic link tab'
        );
    }

    public function test_verify_code_page_renders_digit_inputs(): void
    {
        $html = $this->renderVerifyCode(['email' => 'test@example.com']);

        $this->assertStringContainsString(
            'otp-digit',
            $html,
            'Verify code page should contain OTP digit inputs'
        );
        $this->assertStringContainsString(
            'otp-d1',
            $html,
            'Verify code page should have first digit input'
        );
        $this->assertStringContainsString(
            'otp-d6',
            $html,
            'Verify code page should have sixth digit input'
        );
        $this->assertStringContainsString(
            'name="code"',
            $html,
            'Verify code page should have hidden code field'
        );
    }

    /**
     * Regression: OTP request panel must not render remember_me.
     *
     * remember_me for OTP is captured on verify-code.php, not login.php.
     * The checkbox on the OTP request panel was a no-op (P2 fix).
     */
    public function test_otp_panel_does_not_render_remember_me(): void
    {
        $html = $this->renderLogin();

        // Extract just the OTP panel (between panel-otp and its closing form tag)
        preg_match('/id="panel-otp".*?<\/form>/s', $html, $otpPanel);
        $this->assertNotEmpty($otpPanel, 'OTP panel should exist');

        $this->assertStringNotContainsString(
            'name="remember_me"',
            $otpPanel[0],
            'OTP request panel must not contain remember_me checkbox'
        );
    }

    private function renderVerifyCode(array $vars = []): string
    {
        if (!function_exists('__')) {
            require_once dirname(__DIR__, 2) . '/app/Engine/Locale.php';
            \App\Engine\Locale::loadTranslations(dirname(__DIR__, 2) . '/lang');
        }

        if (!function_exists('app_name')) {
            require_once dirname(__DIR__, 2) . '/app/helpers.php';
        }

        $csrfToken = $vars['csrfToken'] ?? 'test-csrf-token';
        $email = $vars['email'] ?? '';
        $error = $vars['error'] ?? null;
        $success = $vars['success'] ?? null;

        ob_start();
        include dirname(__DIR__, 2) . '/templates/auth/verify-code.php';
        return ob_get_clean() ?: '';
    }

    // ════════════════════════════════════════════════════════════════
    // Demo mode: render-time removal of write surfaces
    // ════════════════════════════════════════════════════════════════

    public function test_demo_mode_hides_otp_magic_link_tabs_and_forgot_link(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vb-demo-login-' . mt_rand();
        @mkdir($tmpDir, 0755, true);
        touch($tmpDir . '/.demo');
        \App\Engine\DemoMode::reset();
        \App\Engine\DemoMode::init($tmpDir);

        $html = $this->renderLogin();

        // Tabs + forms must be removed from DOM
        $this->assertStringNotContainsString('login-method-tabs', $html, 'Demo mode must hide method tabs');
        $this->assertStringNotContainsString('data-method="otp"', $html, 'Demo mode must hide OTP tab');
        $this->assertStringNotContainsString('data-method="magic_link"', $html, 'Demo mode must hide magic-link tab');
        $this->assertStringNotContainsString('panel-otp', $html, 'Demo mode must remove OTP form panel');
        $this->assertStringNotContainsString('panel-magic-link', $html, 'Demo mode must remove magic-link form panel');
        $this->assertStringNotContainsString('forgot-password', $html, 'Demo mode must hide forgot-password link');

        // Password form must still render
        $this->assertStringContainsString('panel-password', $html, 'Password form must still render in demo mode');

        // Demo credential cards must render
        $this->assertStringContainsString('login-demo-credentials', $html, 'Demo mode must render credential cards');
        $this->assertStringContainsString('demo@voxelbooking.com', $html, 'Demo mode must show operator account');

        @unlink($tmpDir . '/.demo');
        @rmdir($tmpDir);
        \App\Engine\DemoMode::reset();
    }

    public function test_normal_mode_shows_otp_magic_link_tabs_and_forgot_link(): void
    {
        \App\Engine\DemoMode::reset();
        \App\Engine\DemoMode::init(sys_get_temp_dir() . '/vb-no-demo-' . mt_rand());

        $html = $this->renderLogin();

        $this->assertStringContainsString('login-method-tabs', $html, 'Normal mode must show method tabs');
        $this->assertStringContainsString('data-method="otp"', $html, 'Normal mode must show OTP tab');
        $this->assertStringContainsString('data-method="magic_link"', $html, 'Normal mode must show magic-link tab');
        $this->assertStringContainsString('forgot-password', $html, 'Normal mode must show forgot-password link');
        $this->assertStringNotContainsString('login-demo-credentials', $html, 'Normal mode must not render demo credentials');

        \App\Engine\DemoMode::reset();
    }
}
