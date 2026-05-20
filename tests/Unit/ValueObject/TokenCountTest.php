<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\ValueObject\ChatTokenCount;
use Token27\Tokenizer\ValueObject\TokenCount;

final class TokenCountTest extends TestCase
{
    #[Test]
    public function token_count_stores_and_returns_all_properties(): void
    {
        $count = new TokenCount(
            count: 42,
            model: 'gpt-4o',
            strategy: 'tiktoken',
            approximate: false,
            encoding: 'o200k_base',
        );

        self::assertSame(42, $count->count());
        self::assertSame('gpt-4o', $count->model());
        self::assertSame('tiktoken', $count->strategy());
        self::assertFalse($count->isApproximate());
    }

    #[Test]
    public function it_detects_fit_within_context_window(): void
    {
        $count = new TokenCount(count: 1000, model: 'gpt-4o', strategy: 'tiktoken');

        self::assertTrue($count->isWithinContextWindow(128_000));
        self::assertFalse($count->isWithinContextWindow(500));
        self::assertTrue($count->isWithinContextWindow(1000));  // exactly at limit
    }

    #[Test]
    public function remaining_tokens_never_goes_negative(): void
    {
        $count = new TokenCount(count: 1000, model: 'gpt-4o', strategy: 'tiktoken');

        self::assertSame(127_000, $count->remainingTokens(128_000));
        self::assertSame(0, $count->remainingTokens(500));      // overflow → 0
        self::assertSame(0, $count->remainingTokens(0));
    }

    #[Test]
    public function percentage_of_is_computed_correctly(): void
    {
        $count = new TokenCount(count: 64_000, model: 'gpt-4o', strategy: 'tiktoken');

        self::assertEqualsWithDelta(0.5, $count->percentageOf(128_000), 0.0001);
        self::assertEqualsWithDelta(1.0, $count->percentageOf(64_000), 0.0001);
        self::assertEqualsWithDelta(2.0, $count->percentageOf(32_000), 0.0001); // overflow
        self::assertSame(0.0, $count->percentageOf(0));
    }

    #[Test]
    #[DataProvider('formatProvider')]
    public function it_formats_correctly(
        int $count,
        string $strategy,
        string $encoding,
        bool $approximate,
        string $expected,
    ): void {
        $result = new TokenCount(
            count: $count,
            model: 'gpt-4o',
            strategy: $strategy,
            approximate: $approximate,
            encoding: $encoding,
        );

        self::assertSame($expected, $result->format());
    }

    /** @return array<string, array{0: int, 1: string, 2: string, 3: bool, 4: string}> */
    public static function formatProvider(): array
    {
        return [
            'exact with encoding' => [2, 'tiktoken', 'o200k_base', false, '2 tokens (tiktoken / o200k_base)'],
            'approx no encoding' => [500, 'char_division', '', true, '~500 tokens (char_division)'],
            'exact no encoding' => [1234, 'hf_json_bpe', '', false, '1,234 tokens (hf_json_bpe)'],
            'large number formatted' => [128000, 'tiktoken', 'cl100k_base', false, '128,000 tokens (tiktoken / cl100k_base)'],
        ];
    }

    #[Test]
    public function to_array_contains_all_keys(): void
    {
        $count = new TokenCount(
            count: 42,
            model: 'gpt-4o',
            strategy: 'tiktoken',
            approximate: false,
            encoding: 'o200k_base',
        );

        $array = $count->toArray();

        self::assertSame(42, $array['count']);
        self::assertSame('gpt-4o', $array['model']);
        self::assertSame('tiktoken', $array['strategy']);
        self::assertFalse($array['approximate']);
        self::assertSame('o200k_base', $array['encoding']);
    }

    #[Test]
    public function chat_token_count_exposes_breakdown(): void
    {
        $count = new ChatTokenCount(
            count: 50,
            contentTokens: 41,
            overheadTokens: 9,
            model: 'gpt-4o',
            strategy: 'tiktoken',
            approximate: false,
            encoding: 'o200k_base',
            messageCount: 2,
        );

        self::assertSame(50, $count->count());
        self::assertSame(41, $count->contentTokens());
        self::assertSame(9, $count->overheadTokens());
        self::assertSame(2, $count->messageCount());
        self::assertStringContainsString('+9 chat overhead', $count->format());
    }

    #[Test]
    public function chat_token_count_to_array_has_extra_keys(): void
    {
        $count = new ChatTokenCount(
            count: 50,
            contentTokens: 41,
            overheadTokens: 9,
            model: 'gpt-4o',
            strategy: 'tiktoken',
            messageCount: 2,
        );

        $array = $count->toArray();

        self::assertArrayHasKey('content_tokens', $array);
        self::assertArrayHasKey('overhead_tokens', $array);
        self::assertArrayHasKey('message_count', $array);
    }
}
