# Contributing to nexus-ai-tokenizer

Contributions are welcome! Please ensure that your changes maintain the zero-config, highly abstracted architecture of the library.

## Architectural Principles

1. **Immutable Results**: `TokenCount` and `ChatTokenCount` must remain strictly immutable Value Objects.
2. **Abstract Providers**: Do not tightly couple code to a specific LLM internally unless placed within a discrete `Strategy` or `Estimator` class.
3. **No Heavy Dependencies**: We do NOT bundle `.json` or `.model` files in the repository. They bloat Composer environments. Strategies for processing these files must accept absolute paths injected heavily during runtime instantiation.
4. **Graceful Degradation**: If an explicit strategy lacks its dependencies (like the SentencePiece FFI plugin), it must never crash the engine. The engine must throw an unsupported exception or the `ModelCatalog` should gracefully route it into `CharDivisionStrategy` as an approximation backup.

## Adding a New Provider Logic

1. Create your `BrandEstimator` inside `src/Vision/` if it adds image functionalities.
2. If it adds a unique string manipulation tokenization algorithm, implement it alongside the siblings in `src/Strategy/`.
3. Update `ModelCatalog` with the regular expression rules routing the `provider-model-*` strings to construct your new Strategy natively.
4. Add corresponding tests.

## Submitting Pull Requests

1. Branch off `main`.
2. Write tests covering both edge cases and optimal conditions.
3. Execute `phpunit` and `phpstan`.
4. Run the CS fixer.
5. Submit the PR with a clean, descriptive summary.

---
[❮ Previous: Testing](testing.md)
