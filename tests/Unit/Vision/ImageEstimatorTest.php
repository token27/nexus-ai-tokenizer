<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Vision;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Vision\AnthropicImageEstimator;
use Token27\Tokenizer\Vision\GeminiImageEstimator;
use Token27\Tokenizer\Vision\OpenAIImageEstimator;

final class ImageEstimatorTest extends TestCase
{
    // ─── OpenAI ──────────────────────────────────────────────────────────────

    #[Test]
    public function openai_supports_gpt4o_and_o1(): void
    {
        $e = new OpenAIImageEstimator();
        self::assertTrue($e->supports('gpt-4o'));
        self::assertTrue($e->supports('gpt-4o-mini'));
        self::assertTrue($e->supports('o1'));
        self::assertTrue($e->supports('o3'));
        self::assertFalse($e->supports('claude-sonnet-4'));
        self::assertFalse($e->supports('gemini-1.5-pro'));
    }

    #[Test]
    public function openai_low_detail_is_always_85(): void
    {
        $e = new OpenAIImageEstimator();
        self::assertSame(85, $e->estimateImageTokens(4096, 4096, 'low', 'gpt-4o')->count());
        self::assertSame(85, $e->estimateImageTokens(100,  100,  'low', 'gpt-4o')->count());
    }

    #[Test]
    public function openai_high_detail_1024x1024_is_765(): void
    {
        // 1024×1024 → longest ≤ 2048 ✓ → shortest (1024) > 768 → scale to 768×768
        // tiles: ceil(768/512) × ceil(768/512) = 2×2 = 4
        // tokens: 85 + 170×4 = 765
        $e = new OpenAIImageEstimator();
        self::assertSame(765, $e->estimateImageTokens(1024, 1024, 'high', 'gpt-4o')->count());
    }

    #[Test]
    public function openai_high_detail_512x512_is_255(): void
    {
        // 512×512 → no scaling needed → 1×1 tile → 85 + 170 = 255
        $e = new OpenAIImageEstimator();
        self::assertSame(255, $e->estimateImageTokens(512, 512, 'high', 'gpt-4o')->count());
    }

    #[Test]
    public function openai_auto_small_image_is_low_detail(): void
    {
        // Both dimensions ≤ 512 → auto resolves to low → 85
        $e = new OpenAIImageEstimator();
        self::assertSame(85, $e->estimateImageTokens(256, 256, 'auto', 'gpt-4o')->count());
    }

    #[Test]
    public function openai_auto_large_image_is_high_detail(): void
    {
        $e    = new OpenAIImageEstimator();
        $auto = $e->estimateImageTokens(1024, 768, 'auto', 'gpt-4o')->count();
        $high = $e->estimateImageTokens(1024, 768, 'high', 'gpt-4o')->count();
        self::assertSame($high, $auto);
    }

    // ─── Anthropic ───────────────────────────────────────────────────────────

    #[Test]
    public function anthropic_supports_claude_models(): void
    {
        $e = new AnthropicImageEstimator();
        self::assertTrue($e->supports('claude-sonnet-4-20250514'));
        self::assertTrue($e->supports('claude-3-opus-20240229'));
        self::assertFalse($e->supports('gpt-4o'));
    }

    #[Test]
    public function anthropic_formula_is_pixels_over_750(): void
    {
        // 1024 × 1024 = 1_048_576 / 750 = 1398.1 → ceil = 1399
        $e = new AnthropicImageEstimator();
        self::assertSame(1399, $e->estimateImageTokens(1024, 1024, 'auto', 'claude-sonnet-4-20250514')->count());
    }

    #[Test]
    public function anthropic_minimum_is_one_token(): void
    {
        // Very small image: 1×1 = 1 / 750 → ceil = 1
        $e = new AnthropicImageEstimator();
        self::assertSame(1, $e->estimateImageTokens(1, 1, 'auto', 'claude-sonnet-4-20250514')->count());
    }

    // ─── Gemini ──────────────────────────────────────────────────────────────

    #[Test]
    public function gemini_supports_gemini_and_gemma(): void
    {
        $e = new GeminiImageEstimator();
        self::assertTrue($e->supports('gemini-1.5-pro'));
        self::assertTrue($e->supports('gemma-2-2b'));
        self::assertFalse($e->supports('gpt-4o'));
    }

    #[Test]
    public function gemini_small_image_is_258(): void
    {
        // Shortest side ≤ 384 → flat 258
        $e = new GeminiImageEstimator();
        self::assertSame(258, $e->estimateImageTokens(256, 256, 'auto', 'gemini-1.5-pro')->count());
        self::assertSame(258, $e->estimateImageTokens(384, 384, 'auto', 'gemini-1.5-pro')->count());
    }

    #[Test]
    public function gemini_large_image_uses_tile_formula(): void
    {
        // 1024×768: shortest=768>384 → tiles = ceil(1024/768)×ceil(768/768) = 2×1 = 2 → 516
        $e = new GeminiImageEstimator();
        self::assertSame(516, $e->estimateImageTokens(1024, 768, 'auto', 'gemini-1.5-pro')->count());
    }

    #[Test]
    public function gemini_square_large_image(): void
    {
        // 1536×1536: shortest=1536>384 → tiles = ceil(1536/768)×ceil(1536/768) = 2×2=4 → 1032
        $e = new GeminiImageEstimator();
        self::assertSame(1032, $e->estimateImageTokens(1536, 1536, 'auto', 'gemini-1.5-pro')->count());
    }
}
