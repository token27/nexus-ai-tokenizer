<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Strategy;

use function count;

use Throwable;
use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Exception\TokenizerLoadException;
use Token27\Tokenizer\ValueObject\ChatTokenCount;
use Token27\Tokenizer\ValueObject\TokenCount;

/**
 * Exact BPE tokenizer wrapping the danny50610/bpe-tokeniser PHP package.
 *
 * Provides accurate token counts for OpenAI models and a good approximation
 * for Anthropic Claude models (which use a similar but unpublished 65K-vocab BPE).
 *
 * REQUIRED PACKAGE (optional for composer, required at runtime):
 *   composer require danny50610/bpe-tokeniser
 *   (~313K installs, PHP ^8.1, last updated 2025-09-21)
 *
 * SUPPORTED ENCODINGS:
 *   - o200k_base  → gpt-4o, gpt-4o-mini, o1, o3, gpt-oss
 *   - cl100k_base → gpt-4, gpt-4-turbo, gpt-3.5-turbo, text-embedding-3*, claude-* (approx.)
 *   - p50k_base   → text-davinci-003, code-davinci-002
 *   - r50k_base   → gpt-2, text-davinci-001
 *
 * CHAT OVERHEAD (OpenAI ChatML spec, source: openai/openai-cookbook):
 *   Every message: +3 tokens (im_start, role, im_end structure)
 *   Name field present: +1 token
 *   Assistant reply priming: +3 tokens at the end
 *   Total for N messages: 3N + 3 tokens overhead
 *
 * CLAUDE APPROXIMATION:
 *   Claude uses a proprietary 65K-vocab BPE (Xenova/claude-tokenizer on HuggingFace,
 *   reverse-engineered). cl100k_base shares 45.2% of its vocabulary with Claude's.
 *   Expected error: ±5–10% on English text, higher on code and non-Latin scripts.
 *   Results are marked isApproximate()=true when used for claude-* models.
 *
 * @example
 *   $strategy = new TiktokenStrategy('o200k_base');
 *   $count = $strategy->count('Hello world', 'gpt-4o');
 *   echo $count->count();  // 2
 *   echo $count->format(); // "2 tokens (tiktoken / o200k_base)"
 */
final class TiktokenStrategy implements TokenizerInterface
{
    /** Encodings where the count is an approximation, not exact. */
    private const APPROXIMATE_MODELS = ['claude-'];

    private mixed $encoder = null;
    private bool $loaded = false;

    public function __construct(
        private readonly string $encoding = 'cl100k_base',
    ) {}

    public function count(string $text, string $model): TokenCountInterface
    {
        $this->ensureLoaded();

        return new TokenCount(
            count: $this->encodeText($text),
            model: $model,
            strategy: $this->getStrategyName(),
            approximate: $this->isApproximateForModel($model),
            encoding: $this->encoding,
        );
    }

    /**
     * @param list<array{role?: string, content?: string, name?: string}> $messages
     */
    public function countChat(array $messages, string $model): ChatTokenCountInterface
    {
        $this->ensureLoaded();

        $contentTokens = 0;

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';
            $content = $message['content'] ?? '';

            $contentTokens += $this->encodeText($role);
            $contentTokens += $this->encodeText($content);

            if (isset($message['name'])) {
                $contentTokens += $this->encodeText($message['name']);
                $contentTokens += 1; // name field token overhead
            }
        }

        // OpenAI ChatML overhead: 3 per message + 3 for assistant priming
        $overhead = count($messages) * 3 + 3;

        return new ChatTokenCount(
            count: $contentTokens + $overhead,
            contentTokens: $contentTokens,
            overheadTokens: $overhead,
            model: $model,
            strategy: $this->getStrategyName(),
            approximate: $this->isApproximateForModel($model),
            encoding: $this->encoding,
            messageCount: count($messages),
        );
    }

    public function supports(string $model): bool
    {
        return true;
    }

    public function getStrategyName(): string
    {
        return 'tiktoken';
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    private function encodeText(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $tokens = $this->encoder->encode($text);

        return is_countable($tokens) ? count($tokens) : 0;
    }

    private function isApproximateForModel(string $model): bool
    {
        foreach (self::APPROXIMATE_MODELS as $prefix) {
            if (str_starts_with($model, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        // danny50610/bpe-tokeniser
        if (!class_exists(\Danny50610\BpeTokeniser\EncodingFactory::class)) {
            throw new TokenizerLoadException(
                "TiktokenStrategy requires the danny50610/bpe-tokeniser package.\n" .
                "Install it with: composer require danny50610/bpe-tokeniser\n" .
                "Without it, the registry automatically falls back to CharDivisionStrategy (±40% error).\n" .
                "See: https://packagist.org/packages/danny50610/bpe-tokeniser",
            );
        }

        try {
            $this->encoder = \Danny50610\BpeTokeniser\EncodingFactory::createByEncodingName($this->encoding);
        } catch (Throwable $e) {
            throw new TokenizerLoadException(
                "Failed to load tiktoken encoding '{$this->encoding}': {$e->getMessage()}.\n" .
                "Ensure danny50610/bpe-tokeniser is correctly installed and the encoding name is valid.\n" .
                "Valid encodings: cl100k_base, o200k_base, p50k_base, r50k_base, p50k_edit",
                previous: $e,
            );
        }

        $this->loaded = true;
    }
}
