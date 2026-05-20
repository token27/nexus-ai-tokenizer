<?php

declare(strict_types=1);

namespace Token27\Tokenizer\ValueObject;

use Token27\Tokenizer\Contract\TokenCountInterface;

/**
 * Immutable result for a single plain-text tokenization.
 *
 * @example
 *   $count = new TokenCount(count: 42, model: 'gpt-4o', strategy: 'tiktoken', encoding: 'o200k_base');
 *   echo $count->format();                        // "42 tokens (tiktoken / o200k_base)"
 *   echo $count->isWithinContextWindow(128_000);  // true
 *   echo $count->remainingTokens(128_000);        // 127958
 */
final readonly class TokenCount implements TokenCountInterface
{
    public function __construct(
        private int    $count,
        private string $model,
        private string $strategy,
        private bool   $approximate = false,
        private string $encoding = '',
    ) {}

    public function count(): int
    {
        return $this->count;
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
        $prefix   = $this->approximate ? '~' : '';
        $numFmt   = number_format($this->count);
        $encSuffix = $this->encoding !== '' ? " / {$this->encoding}" : '';

        return "{$prefix}{$numFmt} tokens ({$this->strategy}{$encSuffix})";
    }

    /** @return array{count: int, model: string, strategy: string, approximate: bool, encoding: string} */
    public function toArray(): array
    {
        return [
            'count'       => $this->count,
            'model'       => $this->model,
            'strategy'    => $this->strategy,
            'approximate' => $this->approximate,
            'encoding'    => $this->encoding,
        ];
    }
}
