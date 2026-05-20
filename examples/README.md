# Examples — nexus-ai-tokenizer

A collection of runnable scripts demonstrating every major component of the `nexus-ai-tokenizer` library in isolation.

## Table of Contents

| Example | Description |
| ------- | ----------- |
| `01-zero-config-openai.php` | The basics: Exact counts for `gpt-4o` / `gpt-3.5-turbo` with built-in zero-config mapping. |
| `02-claude-and-approximate-models.php` | Handling approximate counts (`isApproximate()`) for proprietary Tokenizers like Claude. |
| `03-chat-token-counting.php` | Calculating accurate token overhead across complex ChatML/Role configurations. |
| `04-huggingface-deepseek.php` | Extracting exact math from DeepSeek / Qwen models by providing the `.json` vocabulary file. |
| `05-sentencepiece-gemini.php` | Executing compiled `.model` binaries via FFI plugins for precise Google Gemma/Gemini outputs. |
| `06-image-token-estimation.php` | Math routines proving out High/Low image scaling resolution estimation across models. |
| `07-custom-strategy.php` | Registering your own custom token counting algorithm mappings. |
| `08-custom-provider.php` | Dynamic resolution: Pulling mappings at runtime, e.g., from databases. |
| `09-batch-and-context-window.php` | Validating text limits and automating clean prompt truncation algorithms. |

## Running the Examples

Each example is a fully self-contained script. Ensure your dependencies are installed, then run them directly.

```bash
composer install
php examples/01-zero-config-openai.php
```

---
[❮ Back to Main Documentation](../docs/README.md)
