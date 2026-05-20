<?php

declare(strict_types=1);

/**
 * Example 03 — Counting tokens in a full chat conversation.
 *
 * countChat() counts both content tokens AND provider-specific overhead:
 *   - OpenAI (ChatML): 3 tokens/message + 3 priming tokens
 *   - HuggingFace BPE models (DeepSeek): BOS + role markers
 *   - SentencePiece models: conservative 3/message estimate
 *
 * Use this when estimating API costs for multi-turn conversations.
 *
 * Run: php examples/03-chat-token-counting.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Engine\TokenizerEngine;

$messages = [
    ['role' => 'system',    'content' => 'You are a helpful assistant specialized in PHP development.'],
    ['role' => 'user',      'content' => 'What is the difference between abstract classes and interfaces in PHP?'],
    ['role' => 'assistant', 'content' => 'In PHP, abstract classes can have both abstract and concrete methods, while interfaces can only declare method signatures...'],
    ['role' => 'user',      'content' => 'Can a class implement multiple interfaces?'],
];

$model   = 'gpt-4o';
$builder = TokenizerEngine::for($model);

$chat  = $builder->countChat($messages);
$plain = $builder->count(implode(' ', array_column($messages, 'content')));

echo "=== Chat Token Counting for {$model} ===\n\n";
echo "Messages: " . count($messages) . "\n";
echo "Content tokens:  {$chat->contentTokens()}\n";
echo "Overhead tokens: {$chat->overheadTokens()} (ChatML format markers)\n";
echo "Total tokens:    {$chat->count()}\n";
echo "Plain text would be: {$plain->count()} tokens (no overhead)\n";
echo "Overhead adds:   " . ($chat->count() - $plain->count()) . " extra tokens\n\n";

echo "Formatted: {$chat->format()}\n\n";

echo "--- Breakdown per message (manual) ---\n";
foreach ($messages as $i => $msg) {
    $t = $builder->count($msg['role'] . ' ' . $msg['content'])->count();
    echo "  Message " . ($i + 1) . " [{$msg['role']}]: ~{$t} content tokens\n";
}

echo "\n--- Context window check (128K limit) ---\n";
$limit = 128_000;
echo "Fits within {$limit}: " . ($chat->isWithinContextWindow($limit) ? 'Yes' : 'No') . "\n";
echo "Remaining: {$chat->remainingTokens($limit)} tokens\n";
