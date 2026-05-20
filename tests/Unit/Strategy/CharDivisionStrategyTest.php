<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Strategy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;
use Token27\Tokenizer\ValueObject\ChatTokenCount;

final class CharDivisionStrategyTest extends TestCase
{
    private CharDivisionStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new CharDivisionStrategy();
    }

    #[Test]
    public function it_accepts_any_model(): void
    {
        self::assertTrue($this->strategy->supports('gpt-4o'));
        self::assertTrue($this->strategy->supports('claude-3'));
        self::assertTrue($this->strategy->supports('my-custom-model'));
        self::assertTrue($this->strategy->supports(''));
    }

    #[Test]
    public function it_has_the_correct_strategy_name(): void
    {
        self::assertSame('char_division', $this->strategy->getStrategyName());
    }

    #[Test]
    public function it_marks_results_as_approximate(): void
    {
        $count = $this->strategy->count('Hello world', 'gpt-4o');
        self::assertTrue($count->isApproximate());
    }

    #[Test]
    public function it_returns_zero_for_empty_string(): void
    {
        $count = $this->strategy->count('', 'gpt-4o');
        self::assertSame(0, $count->count());
    }

    #[Test]
    #[DataProvider('textProvider')]
    public function it_estimates_tokens_via_char_division(string $text, int $expectedMin, int $expectedMax): void
    {
        $count = $this->strategy->count($text, 'gpt-4o');
        self::assertGreaterThanOrEqual($expectedMin, $count->count());
        self::assertLessThanOrEqual($expectedMax, $count->count());
    }

    /** @return array<string, array{0: string, 1: int, 2: int}> */
    public static function textProvider(): array
    {
        return [
            'four chars' => ['test', 1, 1],            // ceil(4/4) = 1
            'eight chars' => ['Hello wo', 2, 2],        // ceil(8/4) = 2
            'twelve chars' => ['Hello world!', 3, 3],    // ceil(12/4) = 3
            'longer text' => ['Hello world how are you', 5, 7],
        ];
    }

    #[Test]
    public function it_counts_chat_tokens_with_overhead(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Hello!'],
        ];

        $count = $this->strategy->countChat($messages, 'gpt-4o');

        self::assertInstanceOf(ChatTokenCount::class, $count);
        self::assertTrue($count->isApproximate());
        self::assertSame(2, $count->messageCount());
        self::assertGreaterThan(0, $count->overheadTokens());
        self::assertGreaterThan($count->contentTokens(), $count->count());
    }

    #[Test]
    public function it_includes_model_and_strategy_in_result(): void
    {
        $count = $this->strategy->count('test', 'my-model');
        self::assertSame('my-model', $count->model());
        self::assertSame('char_division', $count->strategy());
    }

    #[Test]
    public function it_formats_result_with_tilde_prefix(): void
    {
        $count = $this->strategy->count('test', 'gpt-4o');
        self::assertStringStartsWith('~', $count->format());
    }

    #[Test]
    public function context_window_helpers_work_correctly(): void
    {
        $count = $this->strategy->count('Hello world', 'gpt-4o'); // ~3 tokens

        self::assertTrue($count->isWithinContextWindow(100));
        self::assertFalse($count->isWithinContextWindow(2));
        self::assertGreaterThan(0, $count->remainingTokens(100));
        self::assertSame(0, $count->remainingTokens(1));
        self::assertGreaterThan(0.0, $count->percentageOf(100));
    }

    #[Test]
    public function to_array_contains_all_required_keys(): void
    {
        $count = $this->strategy->count('test', 'gpt-4o');
        $array = $count->toArray();

        self::assertArrayHasKey('count', $array);
        self::assertArrayHasKey('model', $array);
        self::assertArrayHasKey('strategy', $array);
        self::assertArrayHasKey('approximate', $array);
    }
}
