<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\ValueObject\ChatTokenCount;

final class ChatTokenCountTest extends TestCase
{
    private function make(
        int    $count          = 20,
        int    $contentTokens  = 11,
        int    $overheadTokens = 9,
        string $model          = 'gpt-4o',
        string $strategy       = 'tiktoken',
        bool   $approximate    = false,
        string $encoding       = 'o200k_base',
        int    $messageCount   = 2,
    ): ChatTokenCount {
        return new ChatTokenCount(
            count:          $count,
            contentTokens:  $contentTokens,
            overheadTokens: $overheadTokens,
            model:          $model,
            strategy:       $strategy,
            approximate:    $approximate,
            encoding:       $encoding,
            messageCount:   $messageCount,
        );
    }

    #[Test]
    public function count_returns_total(): void
    {
        self::assertSame(20, $this->make()->count());
    }

    #[Test]
    public function content_tokens_returns_content_only(): void
    {
        self::assertSame(11, $this->make()->contentTokens());
    }

    #[Test]
    public function overhead_tokens_returns_overhead_only(): void
    {
        self::assertSame(9, $this->make()->overheadTokens());
    }

    #[Test]
    public function message_count_is_returned(): void
    {
        self::assertSame(2, $this->make()->messageCount());
    }

    #[Test]
    public function model_strategy_encoding_are_returned(): void
    {
        $c = $this->make();
        self::assertSame('gpt-4o',     $c->model());
        self::assertSame('tiktoken',   $c->strategy());
        self::assertFalse($c->isApproximate());
    }

    #[Test]
    public function is_approximate_true_when_flagged(): void
    {
        self::assertTrue($this->make(approximate: true)->isApproximate());
    }

    #[Test]
    public function is_within_context_window_checks_total_count(): void
    {
        $c = $this->make(count: 20);
        self::assertTrue($c->isWithinContextWindow(20));
        self::assertTrue($c->isWithinContextWindow(100));
        self::assertFalse($c->isWithinContextWindow(19));
    }

    #[Test]
    public function remaining_tokens_is_max_minus_count(): void
    {
        $c = $this->make(count: 20);
        self::assertSame(80, $c->remainingTokens(100));
        self::assertSame(0,  $c->remainingTokens(10)); // clamps at 0
    }

    #[Test]
    public function percentage_of_is_fraction_of_max(): void
    {
        $c = $this->make(count: 50);
        self::assertEqualsWithDelta(0.5, $c->percentageOf(100), 0.0001);
        self::assertSame(0.0, $c->percentageOf(0)); // guard against division by zero
    }

    #[Test]
    public function format_includes_overhead_and_encoding(): void
    {
        $formatted = $this->make(count: 20, overheadTokens: 9, strategy: 'tiktoken', encoding: 'o200k_base')->format();
        self::assertStringContainsString('20', $formatted);
        self::assertStringContainsString('+9 chat overhead', $formatted);
        self::assertStringContainsString('o200k_base', $formatted);
    }

    #[Test]
    public function format_prefixes_tilde_when_approximate(): void
    {
        $formatted = $this->make(approximate: true)->format();
        self::assertStringStartsWith('~', $formatted);
    }

    #[Test]
    public function format_omits_encoding_when_empty(): void
    {
        $formatted = $this->make(encoding: '')->format();
        self::assertStringNotContainsString('/ ', $formatted);
    }

    #[Test]
    public function to_array_contains_all_fields(): void
    {
        $arr = $this->make()->toArray();

        self::assertArrayHasKey('count',           $arr);
        self::assertArrayHasKey('content_tokens',  $arr);
        self::assertArrayHasKey('overhead_tokens', $arr);
        self::assertArrayHasKey('message_count',   $arr);
        self::assertArrayHasKey('model',           $arr);
        self::assertArrayHasKey('strategy',        $arr);
        self::assertArrayHasKey('approximate',     $arr);
        self::assertArrayHasKey('encoding',        $arr);

        self::assertSame(20, $arr['count']);
        self::assertSame(11, $arr['content_tokens']);
        self::assertSame(9,  $arr['overhead_tokens']);
        self::assertSame(2,  $arr['message_count']);
    }
}
