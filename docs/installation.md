# Installation & Setup

Install the library via Composer:

```bash
composer require token27/nexus-ai-tokenizer
```

## Requirements

The core library requires:

- **PHP 8.3** or higher.
- `mbstring` extension.

## Optional Extensions for Exact Counting

By default, the library provides zero-config, exact counting for all OpenAI models via a native Tiktoken implementation.

For other models like **DeepSeek** or **Llama**, exact counting relies on HuggingFace JSON vocabularies. This is handled natively in PHP and requires **no extra extensions**.

However, if you want exact counting for models based on the **SentencePiece** binary format (like **Gemini** / **Gemma**), you must install the following optional dependencies since the format cannot be mapped cleanly using native PHP strings:

1. **System Library**: Install the `sentencepiece` C++ library on your server.
   - Ubuntu/Debian: `sudo apt install libsentencepiece-dev`
   - macOS: `brew install sentencepiece`
2. **PHP Package**: `composer require textualization/sentencepiece`
3. **FFI**: The PHP FFI extension must be enabled.
   - In your `php.ini`: `ffi.enable = "true"`

*If you do not install these, Gemini models will safely fall back to an approximate character-division strategy.*

## First Usage

Start counting text right away without managing any registries. The `TokenizerEngine` handles finding the right model strategy internally:

```php
use Token27\Tokenizer\Engine\TokenizerEngine;

// Ask the engine for the 'gpt-4o' strategy
$count = TokenizerEngine::for('gpt-4o')->count('Hello world');

echo "Used strategy: " . $count->strategy() . "\n";
echo "Tokens count: " . $count->count() . "\n";
```

---
[❮ Previous: Documentation Index](README.md) | [Next: Text & Chat Token Counting ❯](counting-text-and-chat.md)
