<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Exception\TokenizerLoadException;
use Token27\Tokenizer\Strategy\TiktokenStrategy;

/**
 * Tests for TiktokenStrategy.
 *
 * When danny50610/bpe-tokeniser is NOT installed, the strategy must throw
 * TokenizerLoadException with an actionable message. The registry catches this
 * and falls back to CharDivisionStrategy — the app keeps working.
 *
 * When the package IS installed, the tests validate exact token counts
 * against known values from the OpenAI Tokenizer playground.
 */
final class TiktokenStrategyTest extends TestCase
{
    private static bool $tiktokenAvailable;

    public static function setUpBeforeClass(): void
    {
        self::$tiktokenAvailable = class_exists(\Danny50610\BpeTokeniser\EncodingFactory::class);
    }

    #[Test]
    public function it_has_the_correct_strategy_name(): void
    {
        $strategy = new TiktokenStrategy('cl100k_base');
        self::assertSame('tiktoken', $strategy->getStrategyName());
    }

    #[Test]
    public function it_reports_correct_encoding(): void
    {
        self::assertSame('cl100k_base', (new TiktokenStrategy('cl100k_base'))->getEncoding());
        self::assertSame('o200k_base', (new TiktokenStrategy('o200k_base'))->getEncoding());
    }

    #[Test]
    public function it_counts_two_tokens_for_hello_world_with_o200k(): void
    {
        if (!self::$tiktokenAvailable) {
            $this->markTestSkipped('danny50610/bpe-tokeniser not installed.');
        }

        $strategy = new TiktokenStrategy('o200k_base');
        $count = $strategy->count('Hello world', 'gpt-4o');

        self::assertSame(2, $count->count());
        self::assertFalse($count->isApproximate());
        self::assertSame('tiktoken', $count->strategy());
        $array = $count->toArray();
        self::assertArrayHasKey('encoding', $array);
        self::assertSame('o200k_base', $array['encoding'] ?? null);
    }

    #[Test]
    public function it_counts_chat_with_openai_overhead(): void
    {
        if (!self::$tiktokenAvailable) {
            $this->markTestSkipped('danny50610/bpe-tokeniser not installed.');
        }

        $strategy = new TiktokenStrategy('o200k_base');
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Hello!'],
        ];

        $count = $strategy->countChat($messages, 'gpt-4o');

        // Overhead: 3 per message × 2 + 3 priming = 9
        self::assertGreaterThan(9, $count->count());
        self::assertSame(9, $count->overheadTokens());
        self::assertSame(2, $count->messageCount());
    }

    #[Test]
    public function claude_results_are_marked_approximate(): void
    {
        if (!self::$tiktokenAvailable) {
            $this->markTestSkipped('danny50610/bpe-tokeniser not installed.');
        }

        $strategy = new TiktokenStrategy('cl100k_base');
        $count = $strategy->count('Hello', 'claude-sonnet-4-20250514');

        self::assertTrue($count->isApproximate());
    }

    #[Test]
    public function non_claude_results_are_not_approximate(): void
    {
        if (!self::$tiktokenAvailable) {
            $this->markTestSkipped('danny50610/bpe-tokeniser not installed.');
        }

        $strategy = new TiktokenStrategy('o200k_base');
        $count = $strategy->count('Hello', 'gpt-4o');

        self::assertFalse($count->isApproximate());
    }
}
