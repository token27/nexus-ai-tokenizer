<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Bpe\PreTokenizer;

use function chr;
use function in_array;
use function ord;

/**
 * Byte-level BPE pre-tokenizer — used by GPT-2 style models.
 *
 * Maps each byte (0–255) to a unique Unicode character and splits on
 * the GPT-2 regex pattern (which keeps contractions, numbers, spaces as
 * distinct pieces). Used by Falcon, GPT-2 fine-tunes, and some HF models
 * with `"pre_tokenizer": {"type": "ByteLevel"}`.
 *
 * The byte-to-unicode mapping ensures every byte sequence is representable
 * without unknown tokens, avoiding the need for a separate byte_fallback.
 *
 * @example
 *   $pre = new ByteLevelPreTokenizer();
 *   $pre->pretokenize('Hello world');
 *   // ['Hello', 'Ġworld']  (Ġ = U+0120 encodes the space byte 0x20)
 */
final class ByteLevelPreTokenizer implements PreTokenizerInterface
{
    /** @var array<string, string> byte char → unicode char */
    private static ?array $byteToUnicode = null;

    private const SPLIT_PATTERN =
        '/(?i:\'s|\'t|\'re|\'ve|\'m|\'ll|\'d)|[^\r\n\p{L}\p{N}]?\p{L}+|\p{N}{1,3}| ?[^\s\p{L}\p{N}]+[\r\n]*|\s*[\r\n]+|\s+(?!\S)|\s+/u';

    public function __construct(
        private readonly bool $addPrefixSpace = false,
    ) {}

    public function pretokenize(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $map = self::byteToUnicode();
        $input = $this->addPrefixSpace ? ' ' . $text : $text;

        preg_match_all(self::SPLIT_PATTERN, $input, $matches);
        $pieces = $matches[0];

        $result = [];
        foreach ($pieces as $piece) {
            $mapped = '';
            $unpacked = unpack('C*', $piece);
            $bytes = $unpacked !== false ? array_values($unpacked) : [];
            foreach ($bytes as $byte) {
                $mapped .= $map[chr($byte)] ?? chr($byte);
            }
            $result[] = $mapped;
        }

        return $result;
    }

    /** @return array<string, string> */
    public static function byteToUnicode(): array
    {
        if (self::$byteToUnicode !== null) {
            return self::$byteToUnicode;
        }

        // Printable ASCII and Latin-1 supplement (same as GPT-2 bytes_to_unicode())
        $bs = [];
        foreach (range(ord('!'), ord('~')) as $b) {
            $bs[] = $b;
        }
        foreach (range(ord('¡'), ord('¬')) as $b) {
            $bs[] = $b;
        }
        foreach (range(ord('®'), ord('ÿ')) as $b) {
            $bs[] = $b;
        }

        $cs = $bs;
        $n = 0;
        for ($b = 0; $b < 256; $b++) {
            if (!in_array($b, $bs, strict: true)) {
                $bs[] = $b;
                $cs[] = 256 + $n;
                $n++;
            }
        }

        $result = [];
        foreach ($bs as $i => $b) {
            $unicodeChar = mb_chr($cs[$i], 'UTF-8');
            $result[chr($b)] = (string) $unicodeChar;
        }

        return self::$byteToUnicode = $result;
    }
}
