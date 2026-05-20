<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Engine;

use Token27\Tokenizer\Builder\TokenizerBuilder;
use Token27\Tokenizer\Catalog\ModelCatalog;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Contract\TokenizerProviderInterface;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\Strategy\HuggingFaceJsonStrategy;

/**
 * Main entry point for all tokenization operations.
 *
 * Static methods provide zero-config access using the default ModelCatalog registry.
 * Instance methods allow building up custom configurations (returned by static withX() factories).
 *
 * PHP NOTE: a method name cannot be both static and non-static in the same class.
 * Therefore the instance chaining method is named `make()` (not `for()`).
 *
 * ─── ZERO-CONFIG (most common use case) ────────────────────────────────────
 *
 *   $count = TokenizerEngine::for('gpt-4o')->count('Hello world');
 *   echo $count->count();   // 2
 *   echo $count->format();  // "2 tokens (tiktoken / o200k_base)"
 *
 *   $count = TokenizerEngine::for('claude-sonnet-4-20250514')->countChat([
 *       ['role' => 'system', 'content' => 'You are helpful.'],
 *       ['role' => 'user',   'content' => 'What is BPE?'],
 *   ]);
 *   echo $count->isApproximate();  // true (Claude tokenizer is approximated)
 *
 * ─── CUSTOM HuggingFace VOCABULARY ────────────────────────────────────────
 *
 *   $count = TokenizerEngine::withHuggingFaceJson('/opt/models/deepseek-v3/tokenizer.json', 'deepseek-v3')
 *       ->make('deepseek-v3')
 *       ->count('Hello DeepSeek!');
 *
 * ─── CUSTOM STRATEGY ───────────────────────────────────────────────────────
 *
 *   $count = TokenizerEngine::withCustomStrategy('mymodel-*', new MyTokenizer())
 *       ->make('mymodel-v2')
 *       ->count('Hello');
 *
 * ─── CHAINING MULTIPLE CUSTOMIZATIONS ────────────────────────────────────
 *
 *   $engine = TokenizerEngine::withHuggingFaceJson('/path/tokenizer.json', 'deepseek-*')
 *       ->andStrategy('my-internal-model', new MyTokenizer())
 *       ->andProvider(new MyProvider());
 *
 *   $count = $engine->make('deepseek-v3')->count($prompt);
 *
 * ─── CONTEXT WINDOW HELPERS ────────────────────────────────────────────────
 *
 *   $count = TokenizerEngine::for('gpt-4o')->count($prompt);
 *   if (!$count->isWithinContextWindow(128_000)) {
 *       throw new RuntimeException($count->format());
 *   }
 *   echo $count->remainingTokens(128_000) . ' tokens remaining';
 *
 * ─── BATCH COUNTING ────────────────────────────────────────────────────────
 *
 *   $counts = TokenizerEngine::for('gpt-4o')->countBatch(['Hello', 'world']);
 */
final class TokenizerEngine
{
    private function __construct(
        private readonly TokenizerRegistry $registry,
    ) {}

    // ─── Static Entry Points ──────────────────────────────────────────────

    /**
     * Create a builder for a model using the default (ModelCatalog-backed) registry.
     *
     * This is the zero-config entry point for the most common use case:
     *   TokenizerEngine::for('gpt-4o')->count('Hello world')
     *
     * @param string $model The model identifier (e.g., 'gpt-4o', 'claude-sonnet-4-20250514').
     */
    public static function for(string $model): TokenizerBuilder
    {
        return new TokenizerBuilder($model, TokenizerRegistry::createDefault());
    }

    /**
     * Get a list of all model glob patterns supported out-of-the-box.
     *
     * @return string[]
     */
    public static function getKnownPatterns(): array
    {
        return ModelCatalog::getKnownPatterns();
    }

    /**
     * Check if a specific model identifier is natively mapped to a known built-in strategy.
     *
     * Returns true if it matches a registered model pattern, false if it relies on the generic fallback.
     */
    public static function isKnownModel(string $model): bool
    {
        foreach (self::getKnownPatterns() as $pattern) {
            if (fnmatch($pattern, $model)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create a configured engine with a custom strategy for the given pattern.
     *
     * The custom strategy takes priority over the built-in catalog.
     * Chain with ->make($model)->count($text) to tokenize.
     *
     * @param string             $modelPattern Glob pattern, e.g. 'mymodel-*' or 'mymodel-v2'.
     * @param TokenizerInterface $strategy     Strategy to use for matching models.
     *
     * @example
     *   TokenizerEngine::withCustomStrategy('my-llm-*', new MyLlmTokenizer())
     *       ->make('my-llm-v3')
     *       ->count($text);
     */
    public static function withCustomStrategy(string $modelPattern, TokenizerInterface $strategy): self
    {
        $registry = TokenizerRegistry::createDefault();
        $registry->register($modelPattern, $strategy);

        return new self($registry);
    }

    /**
     * Create a configured engine with a HuggingFace tokenizer.json for the given pattern.
     *
     * The tokenizer.json must already exist at the given path (no downloads performed).
     *
     * @param string $jsonPath     Absolute path to tokenizer.json.
     * @param string $modelPattern Glob pattern for which models use this vocabulary.
     *
     * @example
     *   TokenizerEngine::withHuggingFaceJson('/opt/models/deepseek-v3/tokenizer.json', 'deepseek-*')
     *       ->make('deepseek-v3')
     *       ->count('Hello DeepSeek!');
     */
    public static function withHuggingFaceJson(string $jsonPath, string $modelPattern): self
    {
        return self::withCustomStrategy($modelPattern, new HuggingFaceJsonStrategy($jsonPath));
    }

    /**
     * Create a configured engine with a dynamic provider added to the resolution chain.
     *
     *
     * @example
     *   TokenizerEngine::withProvider(new MyModelProvider())
     *       ->make('my-model-v1')
     *       ->count($text);
     */
    public static function withProvider(TokenizerProviderInterface $provider): self
    {
        $registry = TokenizerRegistry::createDefault();
        $registry->addProvider($provider);

        return new self($registry);
    }

    // ─── Instance Methods (use after withX() static factories) ───────────

    /**
     * Create a builder for a model using this engine's configured registry.
     *
     * Named `make()` (not `for()`) because PHP does not allow the same method
     * name to be both static and non-static in the same class.
     *
     * @param string $model The model identifier.
     *
     * @example
     *   TokenizerEngine::withCustomStrategy('my-model', $strategy)
     *       ->make('my-model')
     *       ->count($text);
     */
    public function make(string $model): TokenizerBuilder
    {
        return new TokenizerBuilder($model, $this->registry);
    }

    /**
     * Add a custom strategy to this engine's registry and return a new engine.
     *
     * @param string             $modelPattern Glob pattern.
     * @param TokenizerInterface $strategy     Strategy for matching models.
     *
     * @example
     *   TokenizerEngine::withHuggingFaceJson($path, 'deepseek-*')
     *       ->andStrategy('my-model', new MyTokenizer())
     *       ->make('deepseek-v3')
     *       ->count($text);
     */
    public function andStrategy(string $modelPattern, TokenizerInterface $strategy): self
    {
        $registry = clone $this->registry;
        $registry->register($modelPattern, $strategy);

        return new self($registry);
    }

    /**
     * Add a HuggingFace tokenizer.json to this engine's registry and return a new engine.
     */
    public function andHuggingFaceJson(string $jsonPath, string $modelPattern): self
    {
        return $this->andStrategy($modelPattern, new HuggingFaceJsonStrategy($jsonPath));
    }

    /**
     * Add a dynamic provider to this engine's registry and return a new engine.
     */
    public function andProvider(TokenizerProviderInterface $provider): self
    {
        $registry = clone $this->registry;
        $registry->addProvider($provider);

        return new self($registry);
    }
}
