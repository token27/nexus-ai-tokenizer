<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Bpe;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Bpe\PreTokenizer\MetaspacePreTokenizer;

final class MetaspacePreTokenizerTest extends TestCase
{
    #[Test]
    public function empty_string_returns_empty_array(): void
    {
        $pre = new MetaspacePreTokenizer();
        self::assertSame([], $pre->pretokenize(''));
    }

    #[Test]
    public function single_word_gets_leading_metaspace(): void
    {
        $pre = new MetaspacePreTokenizer();
        self::assertSame(['▁Hello'], $pre->pretokenize('Hello'));
    }

    #[Test]
    public function two_words_each_get_leading_metaspace(): void
    {
        $pre = new MetaspacePreTokenizer();
        self::assertSame(['▁Hello', '▁world'], $pre->pretokenize('Hello world'));
    }

    #[Test]
    public function three_words_split_correctly(): void
    {
        $pre = new MetaspacePreTokenizer();
        self::assertSame(['▁foo', '▁bar', '▁baz'], $pre->pretokenize('foo bar baz'));
    }

    #[Test]
    public function leading_spaces_are_skipped(): void
    {
        $pre = new MetaspacePreTokenizer();
        // "  hello" → replacement chars then 'hello', empty pieces before 'hello' are dropped
        self::assertSame(['▁hello'], $pre->pretokenize('  hello'));
    }

    #[Test]
    public function never_scheme_does_not_prepend_to_first_piece(): void
    {
        $pre = new MetaspacePreTokenizer(prependScheme: 'never');
        self::assertSame(['Hello', '▁world'], $pre->pretokenize('Hello world'));
    }

    #[Test]
    public function split_false_returns_single_piece_with_encoded_spaces(): void
    {
        $pre = new MetaspacePreTokenizer(split: false);
        // Spaces become ▁ but no splitting — one piece returned
        self::assertSame(['▁Hello▁world'], $pre->pretokenize('Hello world'));
    }

    #[Test]
    public function custom_replacement_char_is_used(): void
    {
        $pre = new MetaspacePreTokenizer(replacement: '_', prependScheme: 'first');
        self::assertSame(['_Hello', '_world'], $pre->pretokenize('Hello world'));
    }
}
