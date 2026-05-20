<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Bpe\PreTokenizer;

/**
 * Splits raw text into word-level units before BPE encoding.
 *
 * The pre-tokenizer determines how whitespace and punctuation are handled
 * before the BPE merge algorithm runs. Different model families use
 * different strategies, which changes the resulting tokenization.
 */
interface PreTokenizerInterface
{
    /**
     * Split text into word-level pieces ready for BPE encoding.
     *
     * The returned pieces are fed individually into the BPE engine.
     * Pieces may include leading ▁ (Metaspace) or byte-mapped characters (ByteLevel).
     *
     * @param string $text Raw input text.
     *
     * @return list<string> Pre-tokenized pieces.
     */
    public function pretokenize(string $text): array;
}
