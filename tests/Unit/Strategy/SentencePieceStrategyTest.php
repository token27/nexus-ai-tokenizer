<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Exception\TokenizerLoadException;
use Token27\Tokenizer\Strategy\SentencePieceStrategy;

/**
 * SentencePiece strategy tests.
 *
 * Most tests run without the optional textualization/sentencepiece package.
 * The "missing package" and "missing model file" cases are always testable.
 * Actual tokenization tests are skipped unless the package + model file are present.
 */
final class SentencePieceStrategyTest extends TestCase
{
    #[Test]
    public function it_has_the_correct_strategy_name(): void
    {
        $strategy = new SentencePieceStrategy('/any/path.model');
        self::assertSame('sentencepiece', $strategy->getStrategyName());
    }

    #[Test]
    public function it_supports_all_models(): void
    {
        $strategy = new SentencePieceStrategy('/any/path.model');
        self::assertTrue($strategy->supports('gemini-1.5-pro'));
        self::assertTrue($strategy->supports('llama-2-7b'));
        self::assertTrue($strategy->supports('my-custom-model'));
    }

    #[Test]
    public function it_throws_load_exception_when_package_is_missing(): void
    {
        if (class_exists(\Textualization\SentencePiece\SentencePiece::class)) {
            $this->markTestSkipped('textualization/sentencepiece is installed.');
        }

        $this->expectException(TokenizerLoadException::class);
        $this->expectExceptionMessageMatches('/textualization\/sentencepiece/');

        (new SentencePieceStrategy('/any/path.model'))->count('Hello', 'gemini-1.5-pro');
    }
}
