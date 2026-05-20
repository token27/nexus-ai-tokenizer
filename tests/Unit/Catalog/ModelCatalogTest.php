<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Catalog\ModelCatalog;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;
use Token27\Tokenizer\Strategy\TiktokenStrategy;

final class ModelCatalogTest extends TestCase
{
    #[Test]
    public function catalog_returns_non_empty_array(): void
    {
        $factories = ModelCatalog::getDefaultFactories();
        self::assertNotEmpty($factories);
    }

    #[Test]
    public function all_factories_return_tokenizer_interface(): void
    {
        foreach (ModelCatalog::getDefaultFactories() as $pattern => $factory) {
            $instance = $factory();
            self::assertInstanceOf(
                TokenizerInterface::class,
                $instance,
                "Factory for pattern '{$pattern}' did not return a TokenizerInterface"
            );
        }
    }

    #[Test]
    public function wildcard_fallback_exists(): void
    {
        $factories = ModelCatalog::getDefaultFactories();
        self::assertArrayHasKey('*', $factories);
        self::assertInstanceOf(CharDivisionStrategy::class, $factories['*']());
    }

    /** @return list<array{string, string}> */
    public static function openaiModels(): array
    {
        return [
            ['gpt-4o',              'tiktoken'],
            ['gpt-4o-mini',         'tiktoken'],
            ['o1',                  'tiktoken'],
            ['o3',                  'tiktoken'],
            ['o4-mini',             'tiktoken'],
            ['gpt-4-turbo',         'tiktoken'],
            ['gpt-4',               'tiktoken'],
            ['gpt-3.5-turbo',       'tiktoken'],
            ['text-embedding-3-large', 'tiktoken'],
            ['text-davinci-003',    'tiktoken'],
        ];
    }

    #[Test]
    #[DataProvider('openaiModels')]
    public function openai_models_resolve_to_tiktoken(string $model, string $expectedStrategy): void
    {
        $registry = TokenizerRegistry::createDefault();
        $strategy = $registry->resolveFor($model);
        self::assertSame($expectedStrategy, $strategy->getStrategyName(), "Model: {$model}");
    }

    #[Test]
    public function claude_resolves_to_tiktoken_and_is_approximate(): void
    {
        $registry = TokenizerRegistry::createDefault();
        $strategy = $registry->resolveFor('claude-sonnet-4-20250514');
        self::assertSame('tiktoken', $strategy->getStrategyName());

        // And the result is marked approximate
        $count = $strategy->count('Hello', 'claude-sonnet-4-20250514');
        self::assertTrue($count->isApproximate());
    }

    #[Test]
    public function deepseek_resolves_to_tiktoken_approximation(): void
    {
        $registry = TokenizerRegistry::createDefault();
        $strategy = $registry->resolveFor('deepseek-v3');
        self::assertSame('tiktoken', $strategy->getStrategyName());
    }

    #[Test]
    public function gemini_resolves_to_char_division_by_default(): void
    {
        $registry = TokenizerRegistry::createDefault();
        $strategy = $registry->resolveFor('gemini-1.5-pro');
        self::assertSame('char_division', $strategy->getStrategyName());
    }

    #[Test]
    public function unknown_model_resolves_to_char_division(): void
    {
        $registry = TokenizerRegistry::createDefault();
        $strategy = $registry->resolveFor('totally-unknown-model-xyz');
        self::assertSame('char_division', $strategy->getStrategyName());
    }

    #[Test]
    public function tiktoken_encoding_is_correct_for_gpt4o(): void
    {
        $factories = ModelCatalog::getDefaultFactories();
        $strategy  = $factories['gpt-4o*']();

        self::assertInstanceOf(TiktokenStrategy::class, $strategy);
        self::assertSame('o200k_base', $strategy->getEncoding());
    }

    #[Test]
    public function tiktoken_encoding_is_correct_for_gpt4(): void
    {
        $factories = ModelCatalog::getDefaultFactories();
        $strategy  = $factories['gpt-4*']();

        self::assertInstanceOf(TiktokenStrategy::class, $strategy);
        self::assertSame('cl100k_base', $strategy->getEncoding());
    }
}
