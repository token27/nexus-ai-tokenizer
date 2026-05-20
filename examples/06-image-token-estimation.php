<?php

declare(strict_types=1);

/**
 * Example 06 — Estimating tokens for images in multimodal prompts.
 *
 * Different providers use different formulas to convert image pixels to tokens:
 *
 *   OpenAI (gpt-4o, o1, o3):
 *     low detail:  85 tokens (fixed)
 *     high detail: tiles of 512×512, 85 + 170 per tile
 *
 *   Anthropic (claude-*):
 *     tokens = ceil((width × height) / 750)
 *
 *   Gemini (gemini-*):
 *     short side ≤ 384px: 258 tokens
 *     otherwise: tiles of 768×768, 258 per tile
 *
 * Run: php examples/06-image-token-estimation.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Engine\TokenizerEngine;

$images = [
    ['label' => 'Thumbnail (256×256)',   'w' => 256,  'h' => 256],
    ['label' => 'HD (1024×768)',         'w' => 1024, 'h' => 768],
    ['label' => 'Full HD (1920×1080)',   'w' => 1920, 'h' => 1080],
    ['label' => 'Square (512×512)',      'w' => 512,  'h' => 512],
    ['label' => '4K (3840×2160)',        'w' => 3840, 'h' => 2160],
];

$providers = [
    'gpt-4o'                   => 'OpenAI',
    'claude-sonnet-4-20250514' => 'Anthropic',
    'gemini-1.5-pro'           => 'Gemini',
];

echo str_pad('Image', 22) . str_pad('OpenAI (high)', 16) . str_pad('OpenAI (low)', 15) . str_pad('Anthropic', 12) . "Gemini\n";
echo str_repeat('-', 70) . "\n";

foreach ($images as $img) {
    $high = TokenizerEngine::for('gpt-4o')->estimateImage($img['w'], $img['h'], 'high')->count();
    $low  = TokenizerEngine::for('gpt-4o')->estimateImage($img['w'], $img['h'], 'low')->count();
    $ant  = TokenizerEngine::for('claude-sonnet-4-20250514')->estimateImage($img['w'], $img['h'])->count();
    $gem  = TokenizerEngine::for('gemini-1.5-pro')->estimateImage($img['w'], $img['h'])->count();

    echo str_pad($img['label'], 22)
        . str_pad((string) $high, 16)
        . str_pad((string) $low, 15)
        . str_pad((string) $ant, 12)
        . $gem . "\n";
}

echo "\n--- Multimodal prompt total (text + image) ---\n";
$textTokens  = TokenizerEngine::for('gpt-4o')->count('Describe what you see in this image in detail.')->count();
$imageTokens = TokenizerEngine::for('gpt-4o')->estimateImage(1024, 768, 'high')->count();
$total       = $textTokens + $imageTokens;

echo "Text:    {$textTokens} tokens\n";
echo "Image:   {$imageTokens} tokens (1024×768, high detail)\n";
echo "Total:   {$total} tokens\n";
echo "Budget:  128,000 tokens → " . number_format(128000 - $total) . " remaining\n";
