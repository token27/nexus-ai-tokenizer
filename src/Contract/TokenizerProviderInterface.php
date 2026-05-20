<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Contract;

/**
 * Dynamic factory for tokenizers, used for advanced extensibility.
 *
 * Providers are an alternative to direct strategy registration when
 * strategy creation needs runtime logic (e.g., loading a config file,
 * selecting an encoding based on model version, etc.).
 *
 * The registry tries providers after exhausting static registrations.
 *
 * @example
 *   class MyLlamaProvider implements TokenizerProviderInterface {
 *       public function createFor(string $model): ?TokenizerInterface {
 *           if (!str_starts_with($model, 'llama-')) return null;
 *           $vocabPath = "/models/{$model}/tokenizer.json";
 *           return new HuggingFaceJsonStrategy($vocabPath);
 *       }
 *       public function modelPatterns(): array { return ['llama-*']; }
 *   }
 *
 *   TokenizerEngine::withProvider(new MyLlamaProvider())->for('llama-3.1-70b')->count($text);
 */
interface TokenizerProviderInterface
{
    /**
     * Create a tokenizer for the given model, or null if this provider does not cover it.
     *
     * This method MAY throw TokenizerLoadException if the model is recognized but
     * the required dependency (vocab file, shared library, etc.) is missing.
     *
     * @param string $model The full model identifier.
     *
     * @return TokenizerInterface|null Null means "try the next provider or fallback".
     */
    public function createFor(string $model): ?TokenizerInterface;

    /**
     * Glob patterns for model identifiers this provider covers.
     *
     * Used by the registry to quickly filter providers without calling createFor().
     * The registry calls createFor() only when at least one pattern matches.
     *
     * @return list<string> Patterns like ['llama-3*', 'meta-llama/*'].
     */
    public function modelPatterns(): array;
}
