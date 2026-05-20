# Changelog

All notable changes to `token27/nexus-ai-tokenizer` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [1.0.0] - 2026-05-20

### Added
- `TokenizerEngine` — static facade with zero-config usage for common models
- `TokenizerRegistry` — glob-pattern model→strategy resolution with graceful degradation
- `TokenizerBuilder` — fluent builder returned by `TokenizerEngine::for()`
- `CharDivisionStrategy` — universal fallback (mb_strlen / 4, ±40% error), zero dependencies
- `TiktokenStrategy` — exact BPE for OpenAI models via `danny50610/bpe-tokeniser` (optional)
- `HuggingFaceJsonStrategy` — pure PHP BPE engine reading `tokenizer.json` vocabularies
- `SentencePieceStrategy` — SentencePiece wrapper via `textualization/sentencepiece` (optional)
- `BpeEngine` — pure PHP BPE algorithm with Metaspace and ByteLevel pre-tokenizer support
- `ModelCatalog` — default mappings for OpenAI, Anthropic, Google, DeepSeek, Meta, Mistral, Qwen, xAI, Cohere, Amazon, Microsoft, Nvidia
- `TokenCount` — immutable VO with context-window helpers (isWithinContextWindow, remainingTokens, percentageOf)
- `ChatTokenCount` — immutable VO with content/overhead breakdown for conversation counting
- `OpenAIImageEstimator` — official tile formula (gpt-4o, o1, o3)
- `AnthropicImageEstimator` — official formula (claude-*)
- `GeminiImageEstimator` — tile formula (gemini-*, gemma-*)
- `TokenizerInterface`, `TokenCountInterface`, `ImageTokenEstimatorInterface`, `TokenizerProviderInterface` — public contracts
- `UnsupportedModelException`, `TokenizerLoadException` — typed exceptions
- Full PHPUnit 11 test suite (unit + integration)
- PHP 8.3+ idioms throughout: `readonly class`, `enum`, `match`, named arguments, first-class callables

[Unreleased]: https://github.com/token27/nexus-ai-tokenizer/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/token27/nexus-ai-tokenizer/releases/tag/v1.0.0
