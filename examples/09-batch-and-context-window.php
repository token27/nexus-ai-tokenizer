<?php

declare(strict_types=1);

/**
 * Example 09 — Batch counting and context window management.
 *
 * countBatch() counts multiple texts in one call.
 * Context window helpers let you check if content fits and how much remains.
 *
 * Run: php examples/09-batch-and-context-window.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Engine\TokenizerEngine;

// ── Batch counting ─────────────────────────────────────────────────────────

$documents = [
    'Introduction to BPE tokenization in large language models.',
    'How transformers changed the field of natural language processing.',
    'GPT-4o uses the o200k_base vocabulary with 200,000 token types.',
    'SentencePiece is used by T5, ALBERT, XLNet, and Google models.',
    'The tiktoken library provides fast BPE tokenization for OpenAI models.',
];

$counts = TokenizerEngine::for('gpt-4o')->countBatch($documents);

echo "=== Batch counting (" . count($documents) . " documents) ===\n\n";
$total = 0;
foreach ($counts as $i => $count) {
    $total += $count->count();
    echo "Doc " . ($i + 1) . ": {$count->count()} tokens — \"" . substr($documents[$i], 0, 50) . "...\"\n";
}
echo "\nTotal: {$total} tokens across " . count($documents) . " documents\n";

// ── Context window management ──────────────────────────────────────────────

echo "\n=== Context window management ===\n\n";

$contextLimits = [
    'gpt-4o'                   => 128_000,
    'gpt-4-turbo'              => 128_000,
    'gpt-3.5-turbo'            => 16_385,
    'claude-sonnet-4-20250514' => 200_000,
];

$longPrompt = str_repeat(
    'This is a repeated sentence to simulate a long document that might exceed context windows. ',
    50,
);

echo str_pad('Model', 30) . str_pad('Limit', 10) . str_pad('Used', 10) . str_pad('Remaining', 12) . "Fits?\n";
echo str_repeat('-', 68) . "\n";

foreach ($contextLimits as $model => $limit) {
    $count = TokenizerEngine::for($model)->count($longPrompt);
    $fits  = $count->isWithinContextWindow($limit) ? 'Yes' : 'No';
    $pct   = round($count->percentageOf($limit) * 100, 1);

    echo str_pad($model, 30)
        . str_pad(number_format($limit), 10)
        . str_pad((string) $count->count(), 10)
        . str_pad((string) $count->remainingTokens($limit), 12)
        . "{$fits} ({$pct}%)\n";
}

// ── Truncation helper pattern ──────────────────────────────────────────────

echo "\n=== Truncation pattern ===\n";
echo "(How to trim a prompt to fit within a context window)\n\n";

$builder    = TokenizerEngine::for('gpt-3.5-turbo');
$limit      = 16_385;
$systemMsg  = 'You are a helpful assistant.';
$systemCost = $builder->count($systemMsg)->count() + 9; // +9 for chat overhead
$budget     = $limit - $systemCost - 500; // reserve 500 for the response

echo "Total limit:    {$limit}\n";
echo "System cost:    {$systemCost}\n";
echo "Response buffer: 500\n";
echo "Prompt budget:  {$budget} tokens\n\n";

// Split long text into sentences and add until budget is reached
$sentences   = explode('. ', $longPrompt);
$accumulated = '';
$used        = 0;

foreach ($sentences as $sentence) {
    $candidate = ($accumulated === '' ? '' : ' ') . $sentence . '.';
    $tokenized = $builder->count($accumulated . $candidate)->count();

    if ($tokenized > $budget) {
        break;
    }

    $accumulated .= $candidate;
    $used = $tokenized;
}

echo "Accumulated " . count(explode('. ', $accumulated)) . " sentences → {$used}/{$budget} tokens used\n";
