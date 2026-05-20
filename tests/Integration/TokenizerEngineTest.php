<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Engine\TokenizerEngine;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;
use Token27\Tokenizer\ValueObject\TokenCount;

/**
 * Integration tests for TokenizerEngine.
 *
 * These tests exercise the full flow: Engine → Registry → Strategy → TokenCount.
 * They work with or without optional packages (tiktoken, sentencepiece).
 */
final class TokenizerEngineTest extends TestCase
{
    // ─── Core API ────────────────────────────────────────────────────────

    #[Test]
    public function static_for_returns_a_builder_and_count_works(): void
    {
        $count = TokenizerEngine::for('gpt-4o')->count('Hello world');

        self::assertInstanceOf(TokenCountInterface::class, $count);
        self::assertGreaterThan(0, $count->count());
        self::assertSame('gpt-4o', $count->model());
    }

    #[Test]
    public function count_batch_returns_one_result_per_text(): void
    {
        $texts  = ['Hello', 'world', 'foo bar baz'];
        $counts = TokenizerEngine::for('gpt-4o')->countBatch($texts);

        self::assertCount(3, $counts);
        foreach ($counts as $count) {
            self::assertInstanceOf(TokenCountInterface::class, $count);
            self::assertGreaterThan(0, $count->count());
        }
    }

    #[Test]
    public function count_chat_includes_overhead(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user',   'content' => 'Hello!'],
        ];

        $chat    = TokenizerEngine::for('gpt-4o')->countChat($messages);
        $plain   = TokenizerEngine::for('gpt-4o')->count('You are helpful. Hello!');

        // Chat count must include overhead tokens
        self::assertGreaterThan($plain->count(), $chat->count());
    }

    #[Test]
    public function context_window_helpers_are_fluent_and_correct(): void
    {
        $count = TokenizerEngine::for('gpt-4o')->count('Hello world');

        self::assertTrue($count->isWithinContextWindow(128_000));
        self::assertFalse($count->isWithinContextWindow(1));
        self::assertGreaterThan(0, $count->remainingTokens(128_000));
        self::assertGreaterThan(0.0, $count->percentageOf(128_000));
    }

    // ─── Custom Strategy ──────────────────────────────────────────────────

    #[Test]
    public function with_custom_strategy_is_used_for_matching_model(): void
    {
        $custom = new class implements TokenizerInterface {
            public function count(string $text, string $model): TokenCountInterface
            {
                return new TokenCount(count: 999, model: $model, strategy: 'custom_test');
            }
            public function countChat(array $messages, string $model): ChatTokenCountInterface
            {
                return new \Token27\Tokenizer\ValueObject\ChatTokenCount(
                    count: 999, contentTokens: 999, overheadTokens: 0,
                    model: $model, strategy: 'custom_test',
                );
            }
            public function supports(string $model): bool { return true; }
            public function getStrategyName(): string { return 'custom_test'; }
        };

        $count = TokenizerEngine::withCustomStrategy('my-model-*', $custom)
            ->make('my-model-v1')
            ->count('Hello');

        self::assertSame(999, $count->count());
        self::assertSame('custom_test', $count->strategy());
    }

    #[Test]
    public function with_custom_strategy_does_not_affect_other_models(): void
    {
        $custom = new class implements TokenizerInterface {
            public function count(string $text, string $model): TokenCountInterface
            {
                return new TokenCount(count: 999, model: $model, strategy: 'custom');
            }
            public function countChat(array $messages, string $model): ChatTokenCountInterface
            {
                return new \Token27\Tokenizer\ValueObject\ChatTokenCount(
                    count: 999, contentTokens: 999, overheadTokens: 0,
                    model: $model, strategy: 'custom',
                );
            }
            public function supports(string $model): bool { return true; }
            public function getStrategyName(): string { return 'custom'; }
        };

        $engine = TokenizerEngine::withCustomStrategy('my-model', $custom);

        // gpt-4o should still use the catalog strategy, not the custom one
        $count = $engine->make('gpt-4o')->count('Hello');
        self::assertNotSame('custom', $count->strategy());
    }

    // ─── HuggingFace JSON ─────────────────────────────────────────────────

    #[Test]
    public function with_hugging_face_json_uses_bpe_strategy(): void
    {
        $fixturePath = $this->writeFixtureTokenizer();

        try {
            $count = TokenizerEngine::withHuggingFaceJson($fixturePath, 'my-hf-model')
                ->make('my-hf-model')
                ->count('Hello world');

            self::assertGreaterThan(0, $count->count());
            self::assertSame('hf_json_bpe', $count->strategy());
        } finally {
            unlink($fixturePath);
        }
    }

    // ─── Graceful Degradation ─────────────────────────────────────────────

    #[Test]
    public function unknown_model_falls_back_to_char_division(): void
    {
        $count = TokenizerEngine::for('totally-unknown-model-xyz')->count('Hello world');

        self::assertInstanceOf(TokenCountInterface::class, $count);
        self::assertTrue($count->isApproximate());
        self::assertGreaterThan(0, $count->count());
    }

    #[Test]
    public function image_estimator_returns_positive_count(): void
    {
        $builder = TokenizerEngine::for('gpt-4o');
        $count   = $builder->estimateImage(1024, 768, 'high');

        self::assertGreaterThan(0, $count->count());
    }

    #[Test]
    public function get_strategy_returns_tokenizer_interface(): void
    {
        $strategy = TokenizerEngine::for('gpt-4o')->getStrategy();

        self::assertInstanceOf(TokenizerInterface::class, $strategy);
    }

    #[Test]
    public function engine_chaining_works(): void
    {
        $custom1 = new CharDivisionStrategy();
        $custom2 = new CharDivisionStrategy();

        $engine = TokenizerEngine::withCustomStrategy('model-a', $custom1)
            ->andStrategy('model-b', $custom2);

        $count1 = $engine->make('model-a')->count('Hello');
        $count2 = $engine->make('model-b')->count('Hello');

        self::assertSame('char_division', $count1->strategy());
        self::assertSame('char_division', $count2->strategy());
    }

    // ─── Fixture Helper ───────────────────────────────────────────────────

    private function writeFixtureTokenizer(): string
    {
        $path    = sys_get_temp_dir() . '/nexus_engine_test_' . getmypid() . '.json';
        $fixture = [
            'version'       => '1.0',
            'added_tokens'  => [],
            'normalizer'    => null,
            'pre_tokenizer' => ['type' => 'Metaspace', 'replacement' => '▁', 'prepend_scheme' => 'first', 'split' => true],
            'model' => [
                'type'          => 'BPE',
                'byte_fallback' => false,
                'vocab'         => ['▁' => 0, 'H' => 1, 'e' => 2, 'l' => 3, 'o' => 4, 'w' => 5, 'r' => 6, 'd' => 7, '▁H' => 8, '▁He' => 9, '▁Hel' => 10, '▁Hell' => 11, '▁Hello' => 12, '▁w' => 13, '▁wo' => 14, '▁wor' => 15, '▁worl' => 16, '▁world' => 17],
                'merges'        => ['▁ H', '▁H e', '▁He l', '▁Hel l', '▁Hell o', '▁ w', '▁w o', '▁wo r', '▁wor l', '▁worl d'],
            ],
        ];

        file_put_contents($path, json_encode($fixture, JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
