# nexus-ai-tokenizer — Documentation

Welcome to the full documentation for `token27/nexus-ai-tokenizer`.

## Table of Contents

### Getting Started

| Guide | Description |
|-------|-------------|
| [Installation & Setup](installation.md) | Composer, requirements, and optional extensions |
| [Examples Index](../examples/README.md) | Complete list of runnable code examples |
| [Text & Chat Token Counting](counting-text-and-chat.md) | Counting raw text tokens and ChatML/format overhead |
| [Model Introspection](model-introspection.md) | Querying supported models and verifying fallback mappings |
| [Context Windows](context-window.md) | Enforcing context limits, remaining tokens, and truncation |

### Exact vs Approximate Modalities

| Guide | Description |
|-------|-------------|
| [Exact vs Approximate](exact-vs-approximate.md) | Why approximations exist and how to read `isApproximate()` |
| [HuggingFace BPE](huggingface-bpe.md) | Exact counting for DeepSeek, Llama3, Qwen via `.json` vocabularies |
| [SentencePiece / Gemini](sentencepiece-gemini.md) | Exact counting for Gemini/Gemma models via binary `.model` |
| [Vision & Image Estimation](vision-images.md) | Estimating pixels-to-tokens formulas for OpenAI, Anthropic & Gemini |

### Advanced Usage & Ecosystem

| Guide | Description |
|-------|-------------|
| [Custom Strategies & Providers](custom-strategies.md) | Adding internal tokenizers, dynamic catalogs, and custom algorithms |

### Development & DevOps

| Guide | Description |
|-------|-------------|
| [Testing](testing.md) | Running the test suite |
| [Contributing](contributing.md) | Development setup, standards, and adding new strategies |

## At a Glance

```
nexus-ai-tokenizer/
  src/
    Contract/          # Interfaces (TokenizerInterface, TokenizerProviderInterface, etc.)
    ValueObject/       # Immutable Objects (TokenCount, ChatTokenCount)
    Strategy/          # Tiktoken, CharDivision, HuggingFaceJson, SentencePiece
    Vision/            # OpenAIImageEstimator, AnthropicImageEstimator, GeminiImageEstimator
    Catalog/           # ModelCatalog (Built-in provider to strategy mapping)
    Bpe/               # Native BpeEngine implementation for HuggingFace algorithms
    Builder/           # Fluent TokenizerBuilder
    Engine/            # TokenizerEngine (Main static entry point)
    Registry/          # TokenizerRegistry
    Exception/         # Custom exceptions
```

## Ecosystem Position

`nexus-ai-tokenizer` provides the numerical foundations for managing API capabilities. It allows other libraries in the `nexus-ia-*` ecosystem (such as `nexus-ai-prompts` and `nexus-ai-agents`) to cleanly determine whether a payload fits inside an LLM before attempting costly API calls.

---
[Next: Installation & Setup ❯](installation.md)
