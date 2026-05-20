# HuggingFace BPE Tokenization

While OpenAI uses the binary Tiktoken format, models like **DeepSeek**, **LLaMA**, **Mistral**, and **Qwen** use HuggingFace's BPE representation stored in a gigantic `tokenizer.json` file.

The `nexus-ai-tokenizer` library houses a native `BpeEngine` that interprets these JSON graphs to match the tokenization output exactly without invoking massive Python wrappers mapping via shell commands.

## Getting the `tokenizer.json`

Because these JSON vocabularies can weigh upwards of ~4-5 MB each, **they are not bundled via Composer** to prevent bloating the package size.

You must download the tokenizer JSON from the specific models HuggingFace repository:

*DeepSeek V3 Tokenizer*:
[https://huggingface.co/deepseek-ai/DeepSeek-V3/resolve/main/tokenizer.json](https://huggingface.co/deepseek-ai/DeepSeek-V3/resolve/main/tokenizer.json)

## Loading the JSON for Exact Counting

Use `TokenizerEngine::withHuggingFaceJson()` to map the unbundled file into the model name patterns:

```php
use Token27\Tokenizer\Engine\TokenizerEngine;

// Point the rule at the unbundled DeepSeek JSON file and associate it with 'deepseek-*' models.
$engine = TokenizerEngine::withHuggingFaceJson(
    '/absolute/path/to/models/deepseek-tokenizer.json',
    'deepseek-*'
);

$count = $engine->make('deepseek-v3')->count('BPE Tokenization is optimized for code.');

echo $count->isApproximate(); // false! Exact counts achieved.
echo $count->count(); // e.g. 10
echo $count->strategy(); // huggingface_json
```

If you do NOT provide the `tokenizer.json` for DeepSeek, the library falls back to an approximation using `cl100k_base`.

### Why isn't it bundled?

If we bundled the vocabularies for LLaMA 2, LLaMA 3, Qwen, DeepSeek v2, and DeepSeek v3, the Composer dependency would download 30+ MB of unneeded static files. The decoupled architecture gives you complete architectural choice.

---
[❮ Previous: Exact vs Approximate](exact-vs-approximate.md) | [Next: SentencePiece / Gemini ❯](sentencepiece-gemini.md)
