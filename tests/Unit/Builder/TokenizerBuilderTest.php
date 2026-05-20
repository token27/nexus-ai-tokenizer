<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Builder;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Builder\TokenizerBuilder;
use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;
use Token27\Tokenizer\ValueObject\TokenCount;

final class TokenizerBuilderTest extends TestCase
{
    private function builderWith(TokenizerInterface $strategy, string $model = 'test-model'): TokenizerBuilder
    {
        $registry = new TokenizerRegistry();
        $registry->register($model, $strategy);

        return new TokenizerBuilder($model, $registry);
    }

    private function fixedStrategy(int $count): TokenizerInterface
    {
        return new class($count) implements TokenizerInterface {
            public function __construct(private int $n) {}

            public function count(string $text, string $model): TokenCountInterface
            {
                return new TokenCount(count: $this->n, model: $model, strategy: 'fixed');
            }

            public function countChat(array $messages, string $model): ChatTokenCountInterface
            {
                return new \Token27\Tokenizer\ValueObject\ChatTokenCount(
                    count: $this->n, contentTokens: $this->n,
                    overheadTokens: 0, model: $model, strategy: 'fixed',
                );
            }

            public function supports(string $model): bool { return true; }
            public function getStrategyName(): string { return 'fixed'; }
        };
    }

    #[Test]
    public function count_delegates_to_registry(): void
    {
        $builder = $this->builderWith($this->fixedStrategy(42));
        self::assertSame(42, $builder->count('anything')->count());
    }

    #[Test]
    public function count_chat_delegates_to_registry(): void
    {
        $builder = $this->builderWith($this->fixedStrategy(99));
        $result  = $builder->countChat([['role' => 'user', 'content' => 'Hi']]);
        self::assertSame(99, $result->count());
    }

    #[Test]
    public function count_batch_returns_one_result_per_text(): void
    {
        $builder = $this->builderWith($this->fixedStrategy(5));
        $results = $builder->countBatch(['a', 'b', 'c']);

        self::assertCount(3, $results);
        foreach ($results as $r) {
            self::assertSame(5, $r->count());
        }
    }

    #[Test]
    public function count_batch_empty_returns_empty(): void
    {
        $builder = $this->builderWith($this->fixedStrategy(1));
        self::assertSame([], $builder->countBatch([]));
    }

    #[Test]
    public function get_strategy_returns_resolved_strategy(): void
    {
        $strategy = $this->fixedStrategy(1);
        $builder  = $this->builderWith($strategy);
        self::assertSame('fixed', $builder->getStrategy()->getStrategyName());
    }

    #[Test]
    public function get_model_returns_bound_model(): void
    {
        $builder = $this->builderWith($this->fixedStrategy(1), 'my-model');
        self::assertSame('my-model', $builder->getModel());
    }

    #[Test]
    public function supports_model_true_for_non_char_division(): void
    {
        $builder = $this->builderWith($this->fixedStrategy(1), 'test-model');
        self::assertTrue($builder->supportsModel());
    }

    #[Test]
    public function supports_model_false_when_falls_back_to_char_division(): void
    {
        $registry = new TokenizerRegistry(fallback: new CharDivisionStrategy());
        $builder  = new TokenizerBuilder('unknown-model', $registry);
        self::assertFalse($builder->supportsModel());
    }

    #[Test]
    public function estimate_image_returns_positive_count_for_gpt4o(): void
    {
        $registry = TokenizerRegistry::createDefault();
        $builder  = new TokenizerBuilder('gpt-4o', $registry);
        $count    = $builder->estimateImage(1024, 768, 'high');

        self::assertGreaterThan(0, $count->count());
        self::assertStringContainsString('openai', $count->strategy());
    }

    #[Test]
    public function estimate_image_uses_anthropic_formula_for_claude(): void
    {
        $registry = TokenizerRegistry::createDefault();
        $builder  = new TokenizerBuilder('claude-sonnet-4-20250514', $registry);
        $count    = $builder->estimateImage(1024, 1024, 'auto');

        self::assertGreaterThan(0, $count->count());
        self::assertStringContainsString('anthropic', $count->strategy());
    }

    #[Test]
    public function estimate_image_uses_gemini_formula_for_gemini(): void
    {
        $registry = TokenizerRegistry::createDefault();
        $builder  = new TokenizerBuilder('gemini-1.5-pro', $registry);
        $count    = $builder->estimateImage(1024, 768, 'auto');

        self::assertGreaterThan(0, $count->count());
        self::assertStringContainsString('gemini', $count->strategy());
    }
}
