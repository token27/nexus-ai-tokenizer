<?php

declare(strict_types=1);

namespace Token27\Tokenizer\ValueObject;

use Token27\Tokenizer\Contract\ChatTokenCountInterface;

/**
 * Immutable result for a full chat conversation tokenization.
 *
 * Breaks down total tokens into content (message bodies) and overhead
 * (provider-specific structural tokens like ChatML markers).
 *
 * @example
 *   $count = TokenizerEngine::for('gpt-4o')->countChat([
 *       ['role' => 'system', 'content' => 'You are helpful.'],
 *       ['role' => 'user',   'content' => 'What is BPE?'],
 *   ]);
 *   echo $count->count();          // total: content + overhead
 *   echo $count->contentTokens();  // tokens from message content only
 *   echo $count->overheadTokens(); // tokens added by chat format
 *   echo $count->format();         // "17 tokens (tiktoken / o200k_base, +9 chat overhead)"
 */
final readonly class ChatTokenCount implements ChatTokenCountInterface
{
    public function __construct(
        private int    $count,
        private int    $contentTokens,
        private int    $overheadTokens,
        private string $model,
        private string $strategy,
        private bool   $approximate = false,
        private string $encoding = '',
        private int    $messageCount = 0,
    ) {}

    public function count(): int
    {
        return $this->count;
    }

    /** Tokens from message content only (roles + content text). */
    public function contentTokens(): int
    {
        return $this->contentTokens;
    }

    /** Tokens added by the chat format structure (ChatML, role markers, etc.). */
    public function overheadTokens(): int
    {
        return $this->overheadTokens;
    }

    /** Number of messages in the conversation. */
    public function messageCount(): int
    {
        return $this->messageCount;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function strategy(): string
    {
        return $this->strategy;
    }

    public function isApproximate(): bool
    {
        return $this->approximate;
    }

    public function isWithinContextWindow(int $maxTokens): bool
    {
        return $this->count <= $maxTokens;
    }

    public function remainingTokens(int $maxTokens): int
    {
        return max(0, $maxTokens - $this->count);
    }

    public function percentageOf(int $maxTokens): float
    {
        if ($maxTokens <= 0) {
            return 0.0;
        }

        return $this->count / $maxTokens;
    }

    public function format(): string
    {
        $prefix    = $this->approximate ? '~' : '';
        $numFmt    = number_format($this->count);
        $encSuffix = $this->encoding !== '' ? " / {$this->encoding}" : '';
        $overhead  = $this->overheadTokens > 0 ? ", +{$this->overheadTokens} chat overhead" : '';

        return "{$prefix}{$numFmt} tokens ({$this->strategy}{$encSuffix}{$overhead})";
    }

    /**
     * @return array{
     *     count: int, model: string, strategy: string, approximate: bool,
     *     encoding: string, content_tokens: int, overhead_tokens: int, message_count: int
     * }
     */
    public function toArray(): array
    {
        return [
            'count'           => $this->count,
            'model'           => $this->model,
            'strategy'        => $this->strategy,
            'approximate'     => $this->approximate,
            'encoding'        => $this->encoding,
            'content_tokens'  => $this->contentTokens,
            'overhead_tokens' => $this->overheadTokens,
            'message_count'   => $this->messageCount,
        ];
    }
}
