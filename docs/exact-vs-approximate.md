# Exact vs Approximate Counting

In a perfect world, every LLM provider would open-source their exact tokenization algorithm securely.

In reality, providers like **Anthropic (Claude)** use proprietary closed-source variations of BPE algorithms.

**nexus-ai-tokenizer** explicitly flags whether a count is a 100% mathematically accurate representation of exactly what the API will bill you internally, or whether it's an estimated approximation.

## Understanding `isApproximate()`

When calling `$count->isApproximate()`, you'll receive a boolean:

### **False (Exact Counting)**

This guarantees the tokens returned exactly match the API's internal counting mechanics.

Occurs when using:

1. **OpenAI Models** (`gpt-4o`, `o1`, `gpt-3.5-turbo`): the library natively implements `cl100k_base` and `o200k_base` Tiktoken logic exactly.
2. **DeepSeek / LLaMA / Qwen** (via HuggingFace `.json`): Loading the exact vocabulary produces the exact algorithm graph.
3. **Gemini / Gemma** (via SentencePiece `.model`): Using the FFI bindings invokes the same C++ algorithm as Google.

### **True (Approximate Counting)**

This guarantees the count is an estimate (usually within a ±5% to ±10% margin of error for English, and roughly ±20-40% for character fallbacks).

Occurs when using:

1. **Anthropic Claude Models**: Uses `cl100k_base` as an approximation, as no official Claude tokenizer binary exists natively for PHP.
2. **DeepSeek / Gemini Default Fallbacks**: If you don't provide the explicit vocabulary files via `withHuggingFaceJson()` or SentencePiece, the engine falls back to `cl100k_base` or `CharDivisionStrategy` depending on the model.
3. **Custom "Naive" Strategies**: If you implemented your own simple `explode` logic.

## Why use approximations?

For massive context limits (like Claude's 200k), a ±5% deviance at the 100k mark is ±5k tokens. Since you typically leave a wide buffer for your output generation anyways, an approximation is perfectly viable for safe context truncation without breaking the bank on performance or managing massive vocabulary payloads.

If you absolutely demand exact billing metrics on local open-source models, proceed to the [HuggingFace BPE](huggingface-bpe.md) or [SentencePiece](sentencepiece-gemini.md) guides instead.

---
[❮ Previous: Context Windows](context-window.md) | [Next: HuggingFace BPE ❯](huggingface-bpe.md)
