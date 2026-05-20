<?php

declare(strict_types=1);

/**
 * Example 01 — Zero-config token counting for OpenAI models.
 *
 * No configuration needed. The library ships with built-in mappings for
 * all major OpenAI models (gpt-4o, gpt-4, gpt-3.5-turbo, o1, o3, etc.)
 * using the tiktoken BPE algorithm (exact counts).
 *
 * Run: php examples/01-zero-config-openai.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Engine\TokenizerEngine;

$models = [
    'gpt-4o',
    'gpt-4o-mini',
    'gpt-4-turbo',
    'gpt-4',
    'gpt-3.5-turbo',
    'o1',
    'o3',
];

$text = 'Hello world! This is a test of the token counting library.';

echo "Text: \"{$text}\"\n\n";
echo str_pad('Model', 20) . str_pad('Tokens', 10) . str_pad('Encoding', 15) . "Approximate?\n";
echo str_repeat('-', 55) . "\n";

foreach ($models as $model) {
    $count = TokenizerEngine::for($model)->count($text);

    echo str_pad($model, 20)
        . str_pad((string) $count->count(), 10)
        . str_pad($count->toArray()['encoding'], 15)
        . ($count->isApproximate() ? 'Yes' : 'No')
        . "\n";
}

echo "\n--- Context window helpers ---\n";
$count = TokenizerEngine::for('gpt-4o')->count($text);
$limit = 128_000;

echo "Count:     {$count->count()} tokens\n";
echo "Limit:     {$limit} tokens\n";
echo "Remaining: {$count->remainingTokens($limit)} tokens\n";
echo "Used:      " . round($count->percentageOf($limit) * 100, 4) . "%\n";
echo "Fits:      " . ($count->isWithinContextWindow($limit) ? 'Yes' : 'No') . "\n";
echo "Format:    {$count->format()}\n";
