<?php

declare(strict_types=1);

/**
 * Example 05 — Exact token counting for Gemini (SentencePiece).
 *
 * Gemini uses the same tokenizer as Gemma (SentencePiece binary format).
 * By default, gemini-* models fall back to CharDivisionStrategy (±40% error).
 * For exact counts, register SentencePieceStrategy with the Gemma .model file.
 *
 * REQUIREMENTS:
 *   1. Install the PHP package:
 *        composer require textualization/sentencepiece
 *   2. Install the system library:
 *        Linux:  sudo apt install libsentencepiece-dev
 *        macOS:  brew install sentencepiece
 *   3. Enable PHP FFI extension in php.ini:
 *        ffi.enable = true
 *   4. Download the Gemma tokenizer model:
 *        https://huggingface.co/google/gemma-2-2b/resolve/main/tokenizer.model
 *
 * Run: php examples/05-sentencepiece-gemini.php /path/to/tokenizer.model
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Engine\TokenizerEngine;
use Token27\Tokenizer\Strategy\SentencePieceStrategy;

$modelPath = $argv[1] ?? null;

$text = 'Gemini uses SentencePiece tokenization, the same algorithm as Google\'s Gemma models.';

if ($modelPath === null || !file_exists($modelPath)) {
    echo "Usage: php examples/05-sentencepiece-gemini.php /path/to/gemma/tokenizer.model\n\n";
    echo "Without the model file, showing fallback char_division count:\n\n";

    $count = TokenizerEngine::for('gemini-1.5-pro')->count($text);
    echo "Text:     \"{$text}\"\n";
    echo "Tokens:   {$count->count()} (approximate via char_division, ±40% error)\n";
    echo "Strategy: {$count->strategy()}\n\n";
    echo "To get exact counts, provide the Gemma tokenizer.model file.\n";
    exit(0);
}

if (!class_exists(Textualization\SentencePiece\SentencePiece::class)) {
    echo "textualization/sentencepiece is not installed.\n";
    echo "Run: composer require textualization/sentencepiece\n";
    echo "Also: sudo apt install libsentencepiece-dev && enable ffi in php.ini\n";
    exit(1);
}

$exact   = TokenizerEngine::withCustomStrategy('gemini-*', new SentencePieceStrategy($modelPath))
    ->make('gemini-1.5-pro')
    ->count($text);

$approx  = TokenizerEngine::for('gemini-1.5-pro')->count($text);

echo "Text: \"{$text}\"\n\n";
echo "Exact (SentencePiece): {$exact->count()} tokens\n";
echo "Approx (char_division): {$approx->count()} tokens\n";
echo "Strategy: {$exact->strategy()}\n";
echo "Format:   {$exact->format()}\n";
