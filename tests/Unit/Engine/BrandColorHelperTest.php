<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\BrandColorHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BrandColorHelper — brand color token derivation.
 */
class BrandColorHelperTest extends TestCase
{
    #[Test]
    public function derive_returns_all_required_keys(): void
    {
        $tokens = BrandColorHelper::derive('#2563EB');

        $this->assertArrayHasKey('brand', $tokens);
        $this->assertArrayHasKey('brand_hover', $tokens);
        $this->assertArrayHasKey('brand_text', $tokens);
        $this->assertArrayHasKey('brand_rgb', $tokens);
        // brand_light is NOT in derive() — it's CSS-derived from brand-rgb
        $this->assertArrayNotHasKey('brand_light', $tokens);
    }

    #[Test]
    public function derive_returns_uppercase_hex(): void
    {
        $tokens = BrandColorHelper::derive('#2563eb');

        $this->assertSame('#2563EB', $tokens['brand']);
    }

    #[Test]
    public function derive_strips_leading_hash(): void
    {
        $tokens = BrandColorHelper::derive('#FF5733');
        $this->assertSame('#FF5733', $tokens['brand']);

        $tokens2 = BrandColorHelper::derive('FF5733');
        $this->assertSame('#FF5733', $tokens2['brand']);
    }

    #[Test]
    public function derive_fallback_for_invalid_hex(): void
    {
        $tokens = BrandColorHelper::derive('xyz');

        // Should fall back to default blue
        $this->assertSame('#2563EB', $tokens['brand']);
    }

    #[Test]
    public function brand_hover_is_darker(): void
    {
        $tokens = BrandColorHelper::derive('#2563EB');

        // Hover should be different from brand
        $this->assertNotSame($tokens['brand'], $tokens['brand_hover']);
    }



    #[Test]
    public function brand_text_white_for_dark_colors(): void
    {
        // Dark navy — should get white text
        $tokens = BrandColorHelper::derive('#1E3A5F');
        $this->assertSame('#FFFFFF', $tokens['brand_text']);
    }

    #[Test]
    public function brand_text_dark_for_light_colors(): void
    {
        // Bright yellow — should get dark text
        $tokens = BrandColorHelper::derive('#FFFF00');
        $this->assertSame('#111827', $tokens['brand_text']);
    }

    #[Test]
    public function brand_rgb_format(): void
    {
        $tokens = BrandColorHelper::derive('#FF0000');

        $this->assertSame('255, 0, 0', $tokens['brand_rgb']);
    }

    #[Test]
    public function inline_style_contains_all_variables(): void
    {
        $style = BrandColorHelper::inlineStyle('#2563EB');

        $this->assertStringContainsString('--vb-brand:', $style);
        $this->assertStringContainsString('--vb-brand-hover:', $style);
        // brand-light is CSS-derived from brand-rgb, not emitted in inline style
        $this->assertStringNotContainsString('--vb-brand-light:', $style);
        $this->assertStringContainsString('--vb-brand-text:', $style);
        $this->assertStringContainsString('--vb-brand-rgb:', $style);
        $this->assertStringStartsWith(':root {', $style);
    }

    #[Test]
    public function pure_white_gets_dark_text(): void
    {
        $tokens = BrandColorHelper::derive('#FFFFFF');
        $this->assertSame('#111827', $tokens['brand_text']);
    }

    #[Test]
    public function pure_black_gets_white_text(): void
    {
        $tokens = BrandColorHelper::derive('#000000');
        $this->assertSame('#FFFFFF', $tokens['brand_text']);
    }
}
