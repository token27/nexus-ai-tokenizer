<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Catalog;

use Closure;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;
use Token27\Tokenizer\Strategy\SentencePieceStrategy;
use Token27\Tokenizer\Strategy\TiktokenStrategy;

/**
 * Default model→strategy mappings for the auto-configured registry.
 *
 * Each entry maps a glob pattern to either a factory callable or a strategy instance.
 * Patterns are resolved in descending specificity order (longer patterns win).
 *
 * TOKENIZER TYPES BY PROVIDER (research summary, May 2026):
 *
 *   OpenAI (exact via tiktoken):
 *     gpt-4o*, gpt-4o-mini*, o1*, o3* → o200k_base (200K vocab BPE)
 *     gpt-4*, gpt-3.5*, text-embedding-3* → cl100k_base (100K vocab BPE)
 *     text-davinci-003 → p50k_base
 *
 *   Anthropic (approximation):
 *     claude-* → Proprietary 65K-vocab BPE (Xenova/claude-tokenizer).
 *     We use cl100k_base as approximation (45.2% vocab overlap). Error: ±5–10%.
 *     For exact counts, use Anthropic's POST /v1/messages/count_tokens API endpoint.
 *
 *   Google (SentencePiece, requires model file):
 *     gemini-*, gemma-* → SentencePiece (same tokenizer as Gemma).
 *     Fallback to char_division if model file not registered.
 *
 *   DeepSeek (HuggingFace BPE, 128K vocab):
 *     deepseek-* → HuggingFace JSON BPE (LlamaTokenizerFast, Metaspace).
 *     Fallback to cl100k_base approximation (better than char_division).
 *
 *   Meta LLaMA:
 *     llama-3*, llama3* → tiktoken-format 128K vocab (use cl100k_base as approximation).
 *     llama-2*, llama2* → SentencePiece BPE (Metaspace).
 *
 *   Mistral / Mixtral:
 *     mistral-large*, mistral-nemo* → Tekken tokenizer (tiktoken-format, 131K vocab).
 *     mistral*, mixtral* → SentencePiece v1 (older models).
 *
 *   Qwen (Alibaba):
 *     qwen* → tiktoken cl100k_base equivalent (BPE, Chinese-optimized 150K vocab).
 *     Approximation via cl100k_base; error may be higher for Chinese content.
 *
 *   Cohere:
 *     command* → Proprietary SentencePiece-like BPE. Approximation via char_division.
 *
 *   xAI Grok:
 *     grok* → Proprietary BPE (vocabulary not public). Approximation via cl100k_base.
 *
 *   Amazon Titan:
 *     amazon.titan* → Approximation via cl100k_base.
 *
 *   Fallback:
 *     * → char_division (always available, ±40% error)
 *
 * EXTENDING THE CATALOG:
 *   Use TokenizerEngine::withCustomStrategy() or TokenizerEngine::withProvider()
 *   to register model-specific strategies without modifying this file.
 *
 * UPDATING THIS CATALOG:
 *   When a provider updates their tokenizer (e.g., Anthropic Opus 4.7 redesigned
 *   tokenizer in April 2026), update the mapping here and bump the library version.
 */
final class ModelCatalog
{
    /**
     * Returns glob pattern → TokenizerInterface factory pairs.
     *
     * Factories are closures (not eager instances) so optional dependencies
     * are only loaded when a matching model is actually requested.
     *
     * @return array<string, Closure(): TokenizerInterface>
     */
    public static function getDefaultFactories(): array
    {
        return [
            // ── OpenAI o200k_base ────────────────────────────────────────────
            'gpt-4o*' => static fn() => new TiktokenStrategy('o200k_base'),
            'o1*' => static fn() => new TiktokenStrategy('o200k_base'),
            'o3*' => static fn() => new TiktokenStrategy('o200k_base'),
            'o4*' => static fn() => new TiktokenStrategy('o200k_base'),
            'gpt-oss*' => static fn() => new TiktokenStrategy('o200k_base'),
            'gpt-5*' => static fn() => new TiktokenStrategy('o200k_base'),
            'chatgpt-4o*' => static fn() => new TiktokenStrategy('o200k_base'),

            // ── OpenAI cl100k_base ───────────────────────────────────────────
            'gpt-4-turbo*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'gpt-4*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'gpt-3.5*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'text-embedding-3*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'text-embedding-ada*' => static fn() => new TiktokenStrategy('cl100k_base'),

            // ── OpenAI legacy ────────────────────────────────────────────────
            'text-davinci-003' => static fn() => new TiktokenStrategy('p50k_base'),
            'code-davinci-002' => static fn() => new TiktokenStrategy('p50k_base'),
            'text-davinci-001' => static fn() => new TiktokenStrategy('r50k_base'),

            // ── Anthropic Claude (approximation, cl100k_base) ─────────────────
            // Claude uses a proprietary 65K-vocab BPE (not published).
            // The Xenova/claude-tokenizer reverse-engineered approximation shares
            // 45.2% vocabulary with GPT-4's cl100k_base. Error: ±5–10% on English.
            // TiktokenStrategy marks claude-* results as isApproximate()=true.
            'claude-*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'opus-*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'sonnet-*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'haiku-*' => static fn() => new TiktokenStrategy('cl100k_base'),

            // ── Google Gemini / Gemma (SentencePiece — needs model file) ─────
            // Without a registered .model file, falls back to char_division.
            // Register via: TokenizerEngine::withCustomStrategy('gemini-*', new SentencePieceStrategy('/path/to/tokenizer.model'))
            'gemini-*' => static fn() => new CharDivisionStrategy(),
            'gemma-*' => static fn() => new CharDivisionStrategy(),

            // ── DeepSeek (HuggingFace JSON BPE, 128K vocab) ──────────────────
            // For accurate counts, register via:
            // TokenizerEngine::withHuggingFaceJson('/path/to/tokenizer.json', 'deepseek-*')
            // Default fallback uses cl100k_base (better approximation than char_division).
            'deepseek-*' => static fn() => new TiktokenStrategy('cl100k_base'),

            // ── Meta LLaMA ───────────────────────────────────────────────────
            // LLaMA 3+ uses a 128K tiktoken-format vocab (distinct from cl100k_base).
            // cl100k_base is an approximation; for exact counts use HuggingFaceJsonStrategy.
            'llama-3*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'llama3*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'meta-llama/meta-llama-3*' => static fn() => new TiktokenStrategy('cl100k_base'),
            // LLaMA 2 uses SentencePiece (needs model file)
            'llama-2*' => static fn() => new CharDivisionStrategy(),
            'llama2*' => static fn() => new CharDivisionStrategy(),

            // ── Mistral / Mixtral ────────────────────────────────────────────
            // Mistral large/nemo uses Tekken (131K vocab, tiktoken-format)
            'mistral-large*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'mistral-nemo*' => static fn() => new TiktokenStrategy('cl100k_base'),
            // Older Mistral/Mixtral use SentencePiece
            'mistral*' => static fn() => new CharDivisionStrategy(),
            'mixtral*' => static fn() => new CharDivisionStrategy(),
            'open-mixtral*' => static fn() => new CharDivisionStrategy(),
            'open-mistral*' => static fn() => new CharDivisionStrategy(),

            // ── Qwen (Alibaba) ───────────────────────────────────────────────
            // Qwen2+ uses tiktoken-format BPE with 150K vocab (Byte-Level).
            // cl100k_base is an approximation; Chinese content will have higher error.
            'qwen*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'qwq*' => static fn() => new TiktokenStrategy('cl100k_base'),

            // ── xAI Grok ────────────────────────────────────────────────────
            'grok*' => static fn() => new TiktokenStrategy('cl100k_base'),

            // ── Cohere Command ───────────────────────────────────────────────
            'command*' => static fn() => new CharDivisionStrategy(),
            'c4ai-aya*' => static fn() => new CharDivisionStrategy(),

            // ── Amazon Titan ─────────────────────────────────────────────────
            'amazon.titan*' => static fn() => new TiktokenStrategy('cl100k_base'),

            // ── Microsoft Phi ────────────────────────────────────────────────
            // Phi-3/4 uses tiktoken-format tokenizer
            'phi-*' => static fn() => new TiktokenStrategy('cl100k_base'),
            'microsoft/phi*' => static fn() => new TiktokenStrategy('cl100k_base'),

            // ── Nvidia / Nemotron ────────────────────────────────────────────
            'nemotron*' => static fn() => new TiktokenStrategy('cl100k_base'),

            // ── Universal fallback ───────────────────────────────────────────
            '*' => static fn() => new CharDivisionStrategy(),
        ];
    }

    /**
     * Get all explicitly registered model patterns (excluding the universal fallback).
     *
     * @return string[]
     */
    public static function getKnownPatterns(): array
    {
        $keys = array_keys(self::getDefaultFactories());
        return array_values(array_filter($keys, fn(string $k) => $k !== '*'));
    }
}
