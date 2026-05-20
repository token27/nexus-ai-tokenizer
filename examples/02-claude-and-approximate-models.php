<?php

declare(strict_types=1);

/**
 * Example 02 — Claude and other models with approximate token counts.
 *
 * Claude uses a proprietary tokenizer that is not publicly available.
 * The library uses cl100k_base as an approximation (±5–10% error on English).
 * All approximate results are flagged with isApproximate()=true.
 *
 * Similarly, DeepSeek, LLaMA-3, and Qwen are approximated via cl100k_base
 * unless you provide the exact tokenizer vocabulary (see example 04).
 *
 * Run: php examples/02-claude-and-approximate-models.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Engine\TokenizerEngine;

$text = 'Explain the difference between BPE and SentencePiece tokenization algorithms.';

$models = [
    'claude-sonnet-4-20250514',
    'claude-3-opus-20240229',
    'claude-haiku-4-5-20251001',
    'deepseek-v3',
    'llama-3-70b',
    'qwen-turbo',
];

echo "Text: \"{$text}\"\n\n";
echo str_pad('Model', 35) . str_pad('Tokens', 10) . "Approximate?\n";
echo str_repeat('-', 55) . "\n";

foreach ($models as $model) {
    $count = TokenizerEngine::for($model)->count($text);

    echo str_pad($model, 35)
        . str_pad((string) $count->count(), 10)
        . ($count->isApproximate() ? '~Yes (±5-10%)' : 'No (exact)')
        . "\n";
}

echo "\n--- What isApproximate() means ---\n";
echo "When true: the tokenizer algorithm is an approximation.\n";
echo "  Claude:  proprietary 65K BPE, approximated via cl100k_base.\n";
echo "  DeepSeek: 128K HuggingFace BPE, approximated via cl100k_base.\n";
echo "  For exact DeepSeek counts see examples/04-huggingface-deepseek.php\n";
