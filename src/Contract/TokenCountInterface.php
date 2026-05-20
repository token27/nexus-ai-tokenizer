<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Contract;

/**
 * Rich result object returned by all tokenization operations.
 *
 * Beyond the raw count, exposes context-window helpers and formatting
 * so callers never need to write `$count->count() / $maxTokens` themselves.
 *
 * @example
 *   $result = TokenizerEngine::for('gpt-4o')->count('Hello world');
 *   echo $result->count();                        // 2
 *   echo $result->format();                       // "2 tokens (tiktoken / o200k_base)"
 *   echo $result->isWithinContextWindow(128_000); // true
 *   echo $result->percentageOf(128_000);          // 0.000015625
 *   echo $result->remainingTokens(128_000);       // 127998
 */
interface TokenCountInterface
{
    /** Raw token count. */
    public function count(): int;

    /** Model this count was computed for. */
    public function model(): string;

    /**
     * Algorithm that produced this count.
     *
     * Examples: 'tiktoken', 'hf_json_bpe', 'sentencepiece', 'char_division'.
     */
    public function strategy(): string;

    /**
     * True when the count is a heuristic estimate rather than exact tokenization.
     *
     * Approximate strategies: 'char_division' (±40% error).
     * Near-exact strategies that are still approximations: 'tiktoken' used for Claude (±5%).
     */
    public function isApproximate(): bool;

    /**
     * True when the token count fits within the given context window.
     *
     * @param int $maxTokens Context window size in tokens.
     */
    public function isWithinContextWindow(int $maxTokens): bool;

    /**
     * Tokens still available before hitting the context window limit.
     *
     * Returns 0 if the window is already exceeded (never negative).
     *
     * @param int $maxTokens Context window size in tokens.
     */
    public function remainingTokens(int $maxTokens): int;

    /**
     * Fraction of the context window consumed (0.0 = empty, 1.0 = full, >1.0 = overflow).
     *
     * @param int $maxTokens Context window size in tokens.
     */
    public function percentageOf(int $maxTokens): float;

    /**
     * Human-readable description of this count.
     *
     * Examples:
     *   "2 tokens (tiktoken / o200k_base)"
     *   "~1,234 tokens (char_division)"
     *   "17 tokens (tiktoken / cl100k_base, +6 chat overhead)"
     */
    public function format(): string;

    /**
     * Serializable representation.
     *
     * @return array{count: int, model: string, strategy: string, approximate: bool, encoding?: string}
     */
    public function toArray(): array;
}
