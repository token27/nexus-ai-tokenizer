<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Bpe;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Bpe\BpeEngine;

final class BpeEngineTest extends TestCase
{
    /**
     * Minimal vocab + merges that spell out "Hello world" with Metaspace (▁) prefix.
     *
     * @return array{vocab: array<string,int>, merges: list<string>}
     */
    private function helloWorldFixture(): array
    {
        $chars = ['▁', 'H', 'e', 'l', 'o', 'w', 'r', 'd'];
        $vocab = [];
        $id    = 0;
        foreach ($chars as $ch) {
            $vocab[$ch] = $id++;
        }
        $vocab['▁H']     = $id++;
        $vocab['▁He']    = $id++;
        $vocab['▁Hel']   = $id++;
        $vocab['▁Hell']  = $id++;
        $vocab['▁Hello'] = $id++;
        $vocab['▁w']     = $id++;
        $vocab['▁wo']    = $id++;
        $vocab['▁wor']   = $id++;
        $vocab['▁worl']  = $id++;
        $vocab['▁world'] = $id++;

        $merges = [
            '▁ H',     // ▁H
            '▁H e',    // ▁He
            '▁He l',   // ▁Hel
            '▁Hel l',  // ▁Hell
            '▁Hell o', // ▁Hello
            '▁ w',     // ▁w
            '▁w o',    // ▁wo
            '▁wo r',   // ▁wor
            '▁wor l',  // ▁worl
            '▁worl d', // ▁world
        ];

        return ['vocab' => $vocab, 'merges' => $merges];
    }

    #[Test]
    public function empty_word_returns_zero(): void
    {
        $f  = $this->helloWorldFixture();
        $e  = new BpeEngine($f['vocab'], $f['merges']);
        self::assertSame(0, $e->countTokensInWord(''));
    }

    #[Test]
    public function single_known_char_returns_one(): void
    {
        $f = $this->helloWorldFixture();
        $e = new BpeEngine($f['vocab'], $f['merges']);
        self::assertSame(1, $e->countTokensInWord('H'));
    }

    #[Test]
    public function hello_merges_down_to_one_token(): void
    {
        $f = $this->helloWorldFixture();
        $e = new BpeEngine($f['vocab'], $f['merges']);
        // ▁Hello is one merged token
        self::assertSame(1, $e->countTokensInWord('▁Hello'));
    }

    #[Test]
    public function world_merges_down_to_one_token(): void
    {
        $f = $this->helloWorldFixture();
        $e = new BpeEngine($f['vocab'], $f['merges']);
        self::assertSame(1, $e->countTokensInWord('▁world'));
    }

    #[Test]
    public function word_with_no_merges_stays_as_individual_chars(): void
    {
        $f = $this->helloWorldFixture();
        $e = new BpeEngine($f['vocab'], $f['merges']);
        // 'Hel' has no merge rule for 'H'+'e' or 'e'+'l' in the fixture
        // Each char is a separate known token → 3 tokens
        self::assertSame(3, $e->countTokensInWord('Hel'));
    }

    #[Test]
    public function word_cache_is_used_on_repeat(): void
    {
        $f = $this->helloWorldFixture();
        $e = new BpeEngine($f['vocab'], $f['merges']);

        $first  = $e->countTokensInWord('▁Hello');
        $second = $e->countTokensInWord('▁Hello');

        self::assertSame($first, $second);
    }

    #[Test]
    public function clear_cache_does_not_change_results(): void
    {
        $f = $this->helloWorldFixture();
        $e = new BpeEngine($f['vocab'], $f['merges']);

        $before = $e->countTokensInWord('▁Hello');
        $e->clearCache();
        $after  = $e->countTokensInWord('▁Hello');

        self::assertSame($before, $after);
    }

    #[Test]
    public function byte_backup_unknown_char_is_handled(): void
    {
        // Vocab with byte tokens for common byte values
        $vocab = [
            '<0xC3>' => 0, // UTF-8 first byte of é (U+00E9)
            '<0xA9>' => 1, // UTF-8 second byte of é
        ];
        $e = new BpeEngine($vocab, [], byteBackup: true);
        // 'é' (U+00E9) → 2 bytes → 2 byte tokens
        self::assertSame(2, $e->countTokensInWord('é'));
    }

    #[Test]
    public function no_byte_backup_unknown_char_counts_as_one(): void
    {
        $vocab = ['a' => 0]; // 'é' not in vocab
        $e     = new BpeEngine($vocab, [], byteBackup: false);
        self::assertSame(1, $e->countTokensInWord('é'));
    }
}
