<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Bpe;

use function count;
use function sprintf;

/**
 * Pure PHP BPE (Byte-Pair Encoding) engine.
 *
 * Implements the BPE merge algorithm used by HuggingFace tokenizers.
 * Takes a vocabulary and a ranked list of merge rules, then applies
 * iterative pair merging to compute the final token count for a word.
 *
 * Designed for use with HuggingFace tokenizer.json files (DeepSeek, LLaMA,
 * Mistral, Qwen, Falcon, etc.). NOT for Tiktoken encodings — use TiktokenStrategy
 * for OpenAI models, which has its own optimised binary format.
 *
 * Memory usage per loaded tokenizer:
 *   128K vocab  ≈ 10–15 MB (PHP array overhead included)
 *   100K merges ≈  8–12 MB
 *   Total       ≈ 18–27 MB — acceptable for server PHP (typical limit: 256 MB)
 *
 * Performance:
 *   Uses per-word result caching so repeated words (common in code and templates)
 *   are only BPE-encoded once. For typical prompts (< 100K chars), total time < 1s.
 *
 * @internal Used exclusively by HuggingFaceJsonStrategy.
 */
final class BpeEngine
{
    /** @var array<string, int> merge pair "a b" → rank index */
    private readonly array $mergesRank;

    /** @var array<string, int> token string → token id */
    private readonly array $vocab;

    /** @var array<string, int> word → final token count (per-request cache) */
    private array $wordCache = [];

    /**
     * @param array<string, int> $vocab   Token string to ID mapping from tokenizer.json model.vocab.
     * @param list<string>       $merges  Ordered merge rules from tokenizer.json model.merges.
     *                                   Each entry is "a b" (space-separated token pair).
     * @param bool               $byteBackup When true, characters absent from vocab are decomposed
     *                                        into byte tokens "<0xXX>" (byte_fallback in HF spec).
     */
    public function __construct(
        array $vocab,
        array $merges,
        private bool $byteBackup = true,
    ) {
        $this->vocab = $vocab;

        $rank = [];
        foreach ($merges as $index => $merge) {
            $rank[$merge] = $index;
        }
        $this->mergesRank = $rank;
    }

    /**
     * Count BPE tokens in a pre-tokenized word.
     *
     * The word must already be in post-pre-tokenizer form (e.g., "▁Hello" after Metaspace,
     * or byte-mapped unicode string after ByteLevel pre-tokenizer).
     *
     * Caches results per word for performance on repeated occurrences.
     *
     * @param string $word A single pre-tokenized piece.
     *
     * @return int Number of BPE tokens the word encodes to.
     */
    public function countTokensInWord(string $word): int
    {
        if (isset($this->wordCache[$word])) {
            return $this->wordCache[$word];
        }

        $chars = $this->buildInitialChars($word);

        if ($chars === []) {
            return $this->wordCache[$word] = 0;
        }

        if (count($chars) === 1) {
            return $this->wordCache[$word] = 1;
        }

        $result = $this->applyMerges($chars);

        return $this->wordCache[$word] = count($result);
    }

    /**
     * @return list<string>
     */
    private function buildInitialChars(string $word): array
    {
        $chars = mb_str_split($word, 1, 'UTF-8');
        $result = [];

        foreach ($chars as $char) {
            if (isset($this->vocab[$char])) {
                $result[] = $char;
                continue;
            }

            if ($this->byteBackup) {
                $unpacked = unpack('C*', $char);
                $bytes = $unpacked !== false ? array_values($unpacked) : [];
                foreach ($bytes as $byte) {
                    $byteToken = sprintf('<0x%02X>', $byte);
                    $result[] = isset($this->vocab[$byteToken]) ? $byteToken : $char;
                }
                continue;
            }

            // Unknown character, no byte fallback — treat as a single unknown token
            $result[] = $char;
        }

        return $result;
    }

    /**
     * Apply BPE merge rules iteratively until no more merges apply.
     *
     * At each step, finds the lowest-rank merge pair among all adjacent pairs
     * and merges it. O(n²) per word in the worst case, but words are typically
     * short (< 30 chars) so this is negligible in practice.
     *
     * @param list<string> $chars Initial character list.
     *
     * @return list<string> Final token list after all applicable merges.
     */
    private function applyMerges(array $chars): array
    {
        while (count($chars) > 1) {
            $bestRank = PHP_INT_MAX;
            $bestIdx = -1;
            $n = count($chars) - 1;

            for ($i = 0; $i < $n; $i++) {
                $key = $chars[$i] . ' ' . $chars[$i + 1];

                if (
                    isset($this->mergesRank[$key])
                    && $this->mergesRank[$key] < $bestRank
                ) {
                    $bestRank = $this->mergesRank[$key];
                    $bestIdx = $i;
                }
            }

            if ($bestIdx === -1) {
                break;
            }

            $merged = $chars[$bestIdx] . $chars[$bestIdx + 1];
            array_splice($chars, $bestIdx, 2, [$merged]);
        }

        return $chars;
    }

    /** Clear the word cache (useful between large batches to free memory). */
    public function clearCache(): void
    {
        $this->wordCache = [];
    }
}
