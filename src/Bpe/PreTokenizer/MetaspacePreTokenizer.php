<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Bpe\PreTokenizer;

/**
 * Metaspace pre-tokenizer — used by LlamaTokenizerFast models.
 *
 * Replaces spaces with the replacement character (▁ U+2581) and splits
 * on whitespace, producing one piece per word with the space encoded as
 * a leading ▁. This matches the SentencePiece convention used by:
 * DeepSeek V2/V3, LLaMA-2, Mistral v1/v2, Qwen, Falcon, Yi, and many others.
 *
 * Implementation matches the HuggingFace tokenizers Metaspace behavior:
 *   prepend_scheme="first"  → ▁ prepended to every piece (including first)
 *   prepend_scheme="always" → same as "first" for non-empty text
 *   prepend_scheme="never"  → no ▁ prepended to first piece
 *
 * @example
 *   $pre = new MetaspacePreTokenizer();
 *   $pre->pretokenize('Hello world');  // ['▁Hello', '▁world']
 *   $pre->pretokenize('  leading  ');  // ['▁leading']
 */
final class MetaspacePreTokenizer implements PreTokenizerInterface
{
    private const REPLACEMENT = '▁'; // U+2581

    public function __construct(
        /** @var non-empty-string */
        private readonly string $replacement = self::REPLACEMENT,
        private readonly string $prependScheme = 'first',
        private readonly bool $split = true,
    ) {}

    public function pretokenize(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $normalized = str_replace(' ', $this->replacement, $text);

        if (!$this->split) {
            return [$this->applyPrepend($normalized, isFirst: true)];
        }

        $rawPieces = explode($this->replacement, $normalized);
        $result = [];
        $isFirst = true;

        foreach ($rawPieces as $piece) {
            if ($piece === '') {
                $isFirst = false;
                continue;
            }

            $result[] = $isFirst
                ? $this->applyPrepend($piece, isFirst: true)
                : $this->replacement . $piece;

            $isFirst = false;
        }

        return $result;
    }

    private function applyPrepend(string $piece, bool $isFirst): string
    {
        return match ($this->prependScheme) {
            'never' => $piece,
            default => $this->replacement . $piece, // 'first' and 'always'
        };
    }
}
