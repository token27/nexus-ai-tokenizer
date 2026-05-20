# nexus-ai-tokenizer

[![CI](https://github.com/token27/nexus-ai-tokenizer/actions/workflows/ci.yml/badge.svg)](https://github.com/token27/nexus-ai-tokenizer/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-Level%208-1f6feb)](https://phpstan.org/)
[![Latest Version](https://img.shields.io/packagist/v/token27/nexus-ai-tokenizer.svg?style=flat-square)](https://packagist.org/packages/token27/nexus-ai-tokenizer)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-134%20passing-brightgreen)](#testing)

A **universal, multi-provider** PHP 8.3+ token counting and context estimation library. Manage and estimate context windows across fragmented AI models with an elegant and extensible API.

## Why nexus-ai-tokenizer?

Different providers use completely different tokenization algorithms. OpenAI relies on Tiktoken (`cl100k_base`, `o200k_base`), DeepSeek uses HuggingFace BPE, Gemini uses SentencePiece, and Claude has a proprietary BPE.

**nexus-ai-tokenizer** solves this fragmentation by:

- **Zero-config OpenAI counting**: Built-in, fast, exact token counting for all modern OpenAI models (`gpt-4o`, `o1`, `gpt-3.5-turbo`, etc.).
- **Consistent Interface**: Handle any provider with one method (`TokenizerEngine::for('model')`).
- **Graceful Approximations**: Seamlessly approximate limits for closed tokenizers (like Claude) using `cl100k_base`.
- **Exact Native Integration**: Directly load `.json` HuggingFace and `.model` SentencePiece vocabularies when you need 100% exact math.
- **Multimodal Counting**: Translates image resolutions and detail settings into exact token costs across providers.
- **Batching & Context Windows**: Easy percentage checks, `isWithinContextWindow()`, and batch token processing.

## Features

- **Built-in Catalog**: Out-of-the-box support mapping 50+ mainstream models to the correct strategy.
- **Tiktoken Strategy**: Rapid exact counts for `o200k_base`, `cl100k_base`, and more.
- **ChatML & Conversation Overhead**: `countChat()` handles exact provider metadata framing.
- **Extensible Architecture**: Define your own custom tokenizer strategies and dynamic providers.
- **Type Safety**: PHPStan Level 8, immutable Value Objects.

## Installation

```bash
composer require token27/nexus-ai-tokenizer
```

**Requires:** PHP 8.3+

*Note: For exact SentencePiece tokenization (e.g. Gemini/Gemma), the `textualization/sentencepiece` extension is optionally required. For HuggingFace vocabularies, no extensions are needed.*

## Quick Start

### 1. Zero-config Token Counting

Count exact tokens for any OpenAI model right out of the box:

```php
use Token27\Tokenizer\Engine\TokenizerEngine;

$text = 'Hello world! This is a test of the token counting library.';

// Automatically resolves 'gpt-4o' to Tiktoken (o200k_base)
$count = TokenizerEngine::for('gpt-4o')->count($text);

echo $count->count(); // 14
echo $count->strategy(); // tiktoken:o200k_base
echo $count->isApproximate() ? 'Yes' : 'No'; // No
```

### 2. Multi-turn Chat Counting

Accurately calculate token payloads including provider-specific overheads (role markers, ChatML syntax):

```php
$messages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => 'What is tokenization?'],
];

$chat = TokenizerEngine::for('gpt-4-turbo')->countChat($messages);

echo $chat->contentTokens();  // 12
echo $chat->overheadTokens(); // 11
echo $chat->count();          // 23
```

### 3. Context Window Management

Verify if prompts will fit within a provider's window limit to handle automatic truncation or provider switching:

```php
$count = TokenizerEngine::for('claude-3-opus-20240229')->count($hugeDocument);
$limit = 200_000;

if ($count->isWithinContextWindow($limit)) {
    echo "Fits! " . $count->remainingTokens($limit) . " tokens left.";
} else {
    echo "Too large by " . ($count->count() - $limit) . " tokens.";
}
```

### 4. Multimodal Image Tokens

Translates image dimensions to required token spend:

```php
// OpenAI High-detail image token math
$imageCost = TokenizerEngine::for('gpt-4o')->estimateImage(1920, 1080, 'high');
echo $imageCost->count(); // e.g. 1105 tokens

// Anthropic logic
$claudeCost = TokenizerEngine::for('claude-sonnet-4-20250514')->estimateImage(1920, 1080);
echo $claudeCost->count(); // e.g. 2765 tokens
```

## Documentation

- [Installation & Setup](docs/installation.md) — Dependencies and optional extensions
- [Text & Chat Token Counting](docs/counting-text-and-chat.md) — Calculating content and formatting overhead
- [Context Windows](docs/context-window.md) — Handling prompt limits and budget calculations
- [Exact vs Approximate Counting](docs/exact-vs-approximate.md) — How closed models are approximated
- [HuggingFace BPE](docs/huggingface-bpe.md) — Using `.json` vocabularies for exact DeepSeek/Llama counts
- [SentencePiece / Gemini](docs/sentencepiece-gemini.md) — Using `.model` files with FFI
- [Vision & Images](docs/vision-images.md) — Estimating tokens for multimodal images
- [Custom Strategies](docs/custom-strategies.md) — Developing dynamic catalogs and implementations
- [Testing](docs/testing.md) — Running the test suite
- [Contributing](docs/contributing.md) — Development guidelines

## License

MIT. See [LICENSE](LICENSE).
