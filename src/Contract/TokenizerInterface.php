<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Contract;

use Token27\Tokenizer\Exception\UnsupportedModelException;

/**
 * Core contract for all tokenization strategies.
 *
 * Implementations must be able to count tokens for plain text and
 * for full chat conversations (with per-provider overhead handling).
 *
 * Built-in implementations:
 *   - CharDivisionStrategy  — zero-dependency fallback, ±40% error
 *   - TiktokenStrategy      — exact BPE for OpenAI models (requires danny50610/bpe-tokeniser)
 *   - HuggingFaceJsonStrategy — exact BPE from tokenizer.json (DeepSeek, LLaMA, Mistral…)
 *   - SentencePieceStrategy — SentencePiece (Gemini, LLaMA-2; requires textualization/sentencepiece)
 *
 * Custom strategy example:
 * @example
 *   class MyTokenizer implements TokenizerInterface {
 *       public function count(string $text, string $model): TokenCountInterface {
 *           return new TokenCount(myCount($text), $model, 'my_algo');
 *       }
 *       public function countChat(array $messages, string $model): ChatTokenCountInterface { ... }
 *       public function supports(string $model): bool { return str_starts_with($model, 'my-'); }
 *       public function getStrategyName(): string { return 'my_algo'; }
 *   }
 *
 *   TokenizerEngine::withCustomStrategy('my-*', new MyTokenizer())->for('my-v1')->count($text);
 */
interface TokenizerInterface
{
    /**
     * Count tokens in a plain text string for the given model.
     *
     * @param string $text  The text to tokenize.
     * @param string $model The model identifier (e.g., 'gpt-4o', 'deepseek-v3').
     *
     * @throws UnsupportedModelException If this strategy does not support the model.
     * @throws \Token27\Tokenizer\Exception\TokenizerLoadException If dependencies are missing.
     *
     * @example
     *   $count = $strategy->count('Hello world', 'gpt-4o');
     *   echo $count->count(); // 2
     */
    public function count(string $text, string $model): TokenCountInterface;

    /**
     * Count tokens in a full chat conversation, including per-provider overhead.
     *
     * OpenAI overhead:  3 tokens per message + 3 for assistant priming.
     * Anthropic overhead: ~3 tokens per message (approximation).
     * DeepSeek overhead: special tokens (BOS, role markers) per message.
     *
     * @param list<array{role?: string, content?: string}> $messages Ordered conversation messages.
     * @param string                                     $model    The model identifier.
     *
     * @throws UnsupportedModelException If this strategy does not support the model.
     * @throws \Token27\Tokenizer\Exception\TokenizerLoadException If dependencies are missing.
     *
     * @example
     *   $count = $strategy->countChat([
     *       ['role' => 'system', 'content' => 'You are helpful.'],
     *       ['role' => 'user',   'content' => 'Explain BPE tokenization.'],
     *   ], 'gpt-4o');
     *   echo $count->count();           // total tokens including overhead
     *   echo $count->format();          // "17 tokens (tiktoken / o200k_base, +9 chat overhead)"
     */
    public function countChat(array $messages, string $model): ChatTokenCountInterface;

    /**
     * Returns true if this strategy can tokenize the given model.
     *
     * The registry uses this method to filter candidate strategies before delegating.
     * Returning true here does NOT guarantee the strategy is installed correctly;
     * the library may throw TokenizerLoadException on first use if a dependency is missing.
     *
     * @param string $model The model identifier.
     */
    public function supports(string $model): bool;

    /**
     * Human-readable name identifying the algorithm.
     *
     * Conventional values: 'tiktoken', 'hf_json_bpe', 'sentencepiece', 'char_division'.
     * Used in TokenCountInterface::format() and TokenCountInterface::strategy().
     */
    public function getStrategyName(): string;
}
