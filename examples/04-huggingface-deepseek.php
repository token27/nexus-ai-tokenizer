<?php

declare(strict_types=1);

/**
 * Example 04 — Exact token counting for DeepSeek (HuggingFace JSON vocabulary).
 *
 * DeepSeek uses a 128K vocabulary BPE tokenizer (LlamaTokenizerFast format).
 * The vocabulary file is NOT bundled in this library — it must be downloaded
 * separately from HuggingFace and provided via withHuggingFaceJson().
 *
 * WHY NOT BUNDLED?
 *   The tokenizer.json weighs ~4 MB. Bundling it would make the library too
 *   heavy as a Composer dependency. The same applies to LLaMA, Mistral, etc.
 *
 * HOW TO DOWNLOAD:
 *   From HuggingFace (requires account):
 *     https://huggingface.co/deepseek-ai/DeepSeek-V3/resolve/main/tokenizer.json
 *
 *   Using the HuggingFace CLI:
 *     pip install huggingface_hub
 *     huggingface-cli download deepseek-ai/DeepSeek-V3 tokenizer.json --local-dir /opt/models/deepseek-v3
 *
 * Run: php examples/04-huggingface-deepseek.php /path/to/tokenizer.json
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Engine\TokenizerEngine;

$tokenizerJsonPath = $argv[1] ?? null;

if ($tokenizerJsonPath === null || !file_exists($tokenizerJsonPath)) {
    echo "Usage: php examples/04-huggingface-deepseek.php /path/to/deepseek/tokenizer.json\n\n";
    echo "Without the file, showing approximate count via default cl100k_base:\n\n";

    $text  = 'DeepSeek V3 uses a 128K vocabulary BPE tokenizer.';
    $count = TokenizerEngine::for('deepseek-v3')->count($text);
    echo "Text:     \"{$text}\"\n";
    echo "Tokens:   {$count->count()} (approximate via cl100k_base)\n";
    echo "Approx:   " . ($count->isApproximate() ? 'Yes — install tokenizer.json for exact counts' : 'No') . "\n";
    exit(0);
}

$text = 'DeepSeek V3 uses a 128K vocabulary BPE tokenizer optimized for code and multilingual content.';

// Register the exact vocabulary for deepseek-* models
$count = TokenizerEngine::withHuggingFaceJson($tokenizerJsonPath, 'deepseek-*')
    ->make('deepseek-v3')
    ->count($text);

// Compare with approximate
$approx = TokenizerEngine::for('deepseek-v3')->count($text);

echo "Text: \"{$text}\"\n\n";
echo "Exact (HuggingFace BPE): {$count->count()} tokens\n";
echo "Approx (cl100k_base):    {$approx->count()} tokens\n";
echo "Difference: " . abs($count->count() - $approx->count()) . " tokens ("
    . round(abs($count->count() - $approx->count()) / $count->count() * 100, 1) . "% error)\n\n";

echo "Strategy: {$count->strategy()}\n";
echo "Format:   {$count->format()}\n";

// Chat counting with the exact vocabulary
$messages = [
    ['role' => 'user',      'content' => '你好，请用中文回答：什么是BPE分词？'],
    ['role' => 'assistant', 'content' => 'BPE（字节对编码）是一种子词分词算法...'],
];

$chat = TokenizerEngine::withHuggingFaceJson($tokenizerJsonPath, 'deepseek-*')
    ->make('deepseek-v3')
    ->countChat($messages);

echo "\nChat (Chinese content):\n";
echo "  Content tokens:  {$chat->contentTokens()}\n";
echo "  Overhead tokens: {$chat->overheadTokens()}\n";
echo "  Total:           {$chat->count()}\n";
