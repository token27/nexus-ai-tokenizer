<?php

declare(strict_types=1);

/**
 * Example 07 — Registering a custom tokenizer strategy.
 *
 * Use withCustomStrategy() when you have your own tokenizer implementation
 * for a proprietary or unsupported model. Patterns support glob syntax
 * (* = any chars, ? = one char).
 *
 * The custom strategy takes priority over the built-in catalog.
 *
 * Run: php examples/07-custom-strategy.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Engine\TokenizerEngine;
use Token27\Tokenizer\ValueObject\ChatTokenCount;
use Token27\Tokenizer\ValueObject\TokenCount;

/**
 * A simple word-based tokenizer (not real BPE, just for illustration).
 * Replace this with your actual tokenizer logic.
 */
final class WordCountTokenizer implements TokenizerInterface
{
    public function count(string $text, string $model): TokenCountInterface
    {
        $words = $text === '' ? 0 : count(preg_split('/\s+/', trim($text)));

        return new TokenCount(
            count: $words,
            model: $model,
            strategy: $this->getStrategyName(),
            approximate: true, // word count ≠ exact BPE tokens
        );
    }

    public function countChat(array $messages, string $model): TokenCountInterface
    {
        $content  = 0;
        foreach ($messages as $msg) {
            $content += count(preg_split('/\s+/', trim($msg['content'] ?? '')));
        }
        $overhead = count($messages) * 2 + 2;

        return new ChatTokenCount(
            count: $content + $overhead,
            contentTokens: $content,
            overheadTokens: $overhead,
            model: $model,
            strategy: $this->getStrategyName(),
            approximate: true,
            messageCount: count($messages),
        );
    }

    public function supports(string $model): bool
    {
        return true;
    }

    public function getStrategyName(): string
    {
        return 'word_count';
    }
}

// ── Register the custom strategy for 'my-model-*' ──────────────────────────

$text  = 'Hello world! This is a custom tokenizer strategy example.';

$count = TokenizerEngine::withCustomStrategy('my-model-*', new WordCountTokenizer())
    ->make('my-model-v2')
    ->count($text);

echo "Text:     \"{$text}\"\n";
echo "Tokens:   {$count->count()} (words)\n";
echo "Strategy: {$count->strategy()}\n";
echo "Approx:   " . ($count->isApproximate() ? 'Yes' : 'No') . "\n\n";

// The custom strategy does NOT affect other models
$gpt = TokenizerEngine::withCustomStrategy('my-model-*', new WordCountTokenizer())
    ->make('gpt-4o')
    ->count($text);

echo "gpt-4o still uses: {$gpt->strategy()} (exact tiktoken)\n";
echo "gpt-4o count:      {$gpt->count()} tokens\n";

// ── Chain multiple custom strategies ───────────────────────────────────────
echo "\n--- Chaining multiple strategies ---\n";

$engine = TokenizerEngine::withCustomStrategy('brand-a-*', new WordCountTokenizer())
    ->andStrategy('brand-b-*', new WordCountTokenizer());

echo "brand-a-v1: " . $engine->make('brand-a-v1')->count($text)->count() . " tokens\n";
echo "brand-b-v1: " . $engine->make('brand-b-v1')->count($text)->count() . " tokens\n";
echo "gpt-4o:     " . $engine->make('gpt-4o')->count($text)->count() . " tokens (uses built-in catalog)\n";
