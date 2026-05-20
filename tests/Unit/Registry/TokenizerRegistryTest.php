<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Registry;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Contract\TokenizerProviderInterface;
use Token27\Tokenizer\Exception\TokenizerLoadException;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;

final class TokenizerRegistryTest extends TestCase
{
    #[Test]
    public function it_creates_default_registry_without_errors(): void
    {
        $registry = TokenizerRegistry::createDefault();
        self::assertInstanceOf(TokenizerRegistry::class, $registry);
    }

    #[Test]
    public function it_resolves_fallback_for_unknown_model(): void
    {
        $registry = TokenizerRegistry::createDefault();
        $strategy = $registry->resolveFor('completely-unknown-model-xyz');

        // Should fall back to char_division (or the catalog's '*' pattern)
        self::assertInstanceOf(TokenizerInterface::class, $strategy);
        self::assertSame('char_division', $strategy->getStrategyName());
    }

    #[Test]
    public function custom_registration_takes_priority_over_catalog(): void
    {
        $registry  = TokenizerRegistry::createDefault();
        $custom    = $this->makeStubStrategy('my_custom');

        $registry->register('gpt-4o', $custom);
        $resolved = $registry->resolveFor('gpt-4o');

        self::assertSame('my_custom', $resolved->getStrategyName());
    }

    #[Test]
    public function glob_pattern_matching_works(): void
    {
        $registry = new TokenizerRegistry();
        $custom   = $this->makeStubStrategy('my_strategy');

        $registry->register('my-model-*', $custom);

        self::assertSame('my_strategy', $registry->resolveFor('my-model-v1')->getStrategyName());
        self::assertSame('my_strategy', $registry->resolveFor('my-model-v2-large')->getStrategyName());
    }

    #[Test]
    public function longer_pattern_wins_over_shorter(): void
    {
        $registry = new TokenizerRegistry();
        $general  = $this->makeStubStrategy('general');
        $specific = $this->makeStubStrategy('specific');

        $registry->register('gpt-4*',    $general);
        $registry->register('gpt-4o*',   $specific);

        // 'gpt-4o*' is longer (6 chars) than 'gpt-4*' (5 chars) → specific wins
        self::assertSame('specific', $registry->resolveFor('gpt-4o-mini')->getStrategyName());
        self::assertSame('general',  $registry->resolveFor('gpt-4-turbo')->getStrategyName());
    }

    #[Test]
    public function it_suppresses_load_exception_and_falls_back(): void
    {
        $registry = new TokenizerRegistry(fallback: new CharDivisionStrategy());

        // Register a broken strategy that throws TokenizerLoadException on count()
        $broken = $this->makeBrokenStrategy();
        $registry->register('broken-model-*', $broken);

        // Should not throw — registry catches TokenizerLoadException and falls back
        $result = $registry->count('some text', 'broken-model-v1');
        self::assertSame('char_division', $result->strategy());

        // Warning should be recorded
        self::assertNotEmpty($registry->getWarnings());
    }

    #[Test]
    public function dynamic_provider_is_queried_when_static_fails(): void
    {
        $registry = new TokenizerRegistry();
        $provider = $this->makeProvider('my-dynamic-*', 'dynamic_strategy');

        $registry->addProvider($provider);

        $strategy = $registry->resolveFor('my-dynamic-model');
        self::assertSame('dynamic_strategy', $strategy->getStrategyName());
    }

    #[Test]
    public function provider_returning_null_falls_through_to_fallback(): void
    {
        $registry = new TokenizerRegistry(fallback: new CharDivisionStrategy());
        $provider = new class implements TokenizerProviderInterface {
            public function createFor(string $model): ?TokenizerInterface { return null; }
            public function modelPatterns(): array { return ['anything-*']; }
        };

        $registry->addProvider($provider);

        $strategy = $registry->resolveFor('anything-goes');
        self::assertSame('char_division', $strategy->getStrategyName());
    }

    #[Test]
    public function registry_itself_implements_tokenizer_interface(): void
    {
        $registry = TokenizerRegistry::createDefault();
        self::assertInstanceOf(TokenizerInterface::class, $registry);
        self::assertTrue($registry->supports('any-model'));
        self::assertSame('registry', $registry->getStrategyName());
    }

    #[Test]
    public function count_delegates_to_resolved_strategy(): void
    {
        $registry = new TokenizerRegistry();
        $custom   = $this->makeStubStrategy('stub', fixedCount: 7);

        $registry->register('stub-model', $custom);

        $result = $registry->count('some text', 'stub-model');
        self::assertSame(7, $result->count());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function makeStubStrategy(string $name, int $fixedCount = 3): TokenizerInterface
    {
        return new class($name, $fixedCount) implements TokenizerInterface {
            public function __construct(
                private readonly string $name,
                private readonly int    $count,
            ) {}

            public function count(string $text, string $model): TokenCountInterface
            {
                return new \Token27\Tokenizer\ValueObject\TokenCount(
                    count: $this->count,
                    model: $model,
                    strategy: $this->name,
                );
            }

            public function countChat(array $messages, string $model): ChatTokenCountInterface
            {
                return new \Token27\Tokenizer\ValueObject\ChatTokenCount(
                    count: $this->count, contentTokens: $this->count,
                    overheadTokens: 0, model: $model, strategy: $this->name,
                );
            }

            public function supports(string $model): bool { return true; }
            public function getStrategyName(): string { return $this->name; }
        };
    }

    private function makeBrokenStrategy(): TokenizerInterface
    {
        return new class implements TokenizerInterface {
            public function count(string $text, string $model): TokenCountInterface
            {
                throw new TokenizerLoadException('Test: required package is not installed. Run: composer require broken/package');
            }

            public function countChat(array $messages, string $model): ChatTokenCountInterface
            {
                throw new TokenizerLoadException('Test: required package is not installed. Run: composer require broken/package');
            }

            public function supports(string $model): bool { return true; }
            public function getStrategyName(): string { return 'broken'; }
        };
    }

    private function makeProvider(string $pattern, string $strategyName): TokenizerProviderInterface
    {
        // Capture stub factory as a closure to avoid private method access from anonymous class
        $stubFactory = fn() => $this->makeStubStrategy($strategyName);

        return new class($pattern, $stubFactory) implements TokenizerProviderInterface {
            public function __construct(
                private readonly string   $pattern,
                private readonly \Closure $stubFactory,
            ) {}

            public function createFor(string $model): ?TokenizerInterface
            {
                return fnmatch($this->pattern, $model)
                    ? ($this->stubFactory)()
                    : null;
            }

            public function modelPatterns(): array { return [$this->pattern]; }
        };
    }
}
