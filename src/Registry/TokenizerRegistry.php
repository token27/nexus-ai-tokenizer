<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Registry;

use Closure;

use function strlen;

use Token27\Tokenizer\Catalog\ModelCatalog;
use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Contract\TokenizerProviderInterface;
use Token27\Tokenizer\Exception\TokenizerLoadException;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;

/**
 * Resolves the best available tokenizer strategy for any model identifier.
 *
 * Resolution order (highest to lowest priority):
 *   1. Exact model ID match among registered patterns
 *   2. Glob pattern match, sorted by pattern length (longer = more specific wins)
 *   3. Dynamic providers (TokenizerProviderInterface), in registration order
 *   4. Global fallback (CharDivisionStrategy by default)
 *
 * When a strategy raises TokenizerLoadException (missing optional dependency),
 * the registry logs the message and tries the next candidate — the application
 * always gets a working tokenizer.
 *
 * Custom strategies override built-in ones when registered after calling
 * createDefault() or when using TokenizerEngine::withCustomStrategy().
 *
 * @example
 *   // Use the pre-configured default registry
 *   $registry = TokenizerRegistry::createDefault();
 *
 *   // Override a specific model
 *   $registry->register('my-custom-model', new MyTokenizer());
 *
 *   // Add a dynamic provider
 *   $registry->addProvider(new MyLlamaProvider());
 *
 *   $count = $registry->count('Hello', 'gpt-4o');
 */
final class TokenizerRegistry implements TokenizerInterface
{
    /**
     * @var array<string, Closure(): TokenizerInterface> pattern → factory (lazily instantiated)
     * Ordered: last registered has highest priority.
     */
    private array $factories = [];

    /** @var array<string, TokenizerInterface> pattern → resolved instance (cache) */
    private array $resolved = [];

    /** @var list<TokenizerProviderInterface> */
    private array $providers = [];

    /** @var list<string> Messages from suppressed TokenizerLoadExceptions. */
    private array $warnings = [];

    public function __construct(
        private readonly TokenizerInterface $fallback = new CharDivisionStrategy(),
    ) {}

    /**
     * Create a registry pre-configured with all built-in model mappings from ModelCatalog.
     *
     * This is the registry used by TokenizerEngine when no custom configuration is provided.
     */
    public static function createDefault(): self
    {
        $registry = new self();

        foreach (ModelCatalog::getDefaultFactories() as $pattern => $factory) {
            $registry->factories[$pattern] = $factory;
        }

        return $registry;
    }

    /**
     * Register a tokenizer strategy for a model pattern.
     *
     * The pattern is matched with fnmatch() (glob-style: * = any chars, ? = one char).
     * Later registrations take priority over earlier ones.
     *
     * @param string              $modelPattern Glob pattern, e.g. 'gpt-4*' or 'mycompany-v2'.
     * @param TokenizerInterface  $strategy     The strategy to use for matching models.
     *
     * @return $this Fluent interface for chaining.
     */
    public function register(string $modelPattern, TokenizerInterface $strategy): self
    {
        $this->factories[$modelPattern] = static fn() => $strategy;
        unset($this->resolved[$modelPattern]); // invalidate cache

        return $this;
    }

    /**
     * Register a lazy factory for a model pattern.
     *
     * Preferred over register() when strategy instantiation is expensive.
     *
     * @param string                            $modelPattern Glob pattern.
     * @param Closure(): TokenizerInterface    $factory      Called on first match.
     *
     * @return $this
     */
    public function registerFactory(string $modelPattern, Closure $factory): self
    {
        $this->factories[$modelPattern] = $factory;
        unset($this->resolved[$modelPattern]);

        return $this;
    }

    /**
     * Add a dynamic provider that is queried when static registrations don't match.
     *
     * @return $this
     */
    public function addProvider(TokenizerProviderInterface $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Retrieve any warnings from suppressed TokenizerLoadExceptions.
     *
     * Useful for logging: "TiktokenStrategy unavailable, falling back to char_division".
     *
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    // ─── TokenizerInterface ─────────────────────────────────────────────────

    public function count(string $text, string $model): TokenCountInterface
    {
        $strategy = $this->resolveFor($model);

        try {
            return $strategy->count($text, $model);
        } catch (TokenizerLoadException $e) {
            $this->warnings[] = "[TokenizerRegistry] '{$strategy->getStrategyName()}' failed for '{$model}': {$e->getMessage()}";

            return $this->fallback->count($text, $model);
        }
    }

    /**
     * @param list<array{role?: string, content?: string}> $messages
     */
    public function countChat(array $messages, string $model): ChatTokenCountInterface
    {
        $strategy = $this->resolveFor($model);

        try {
            return $strategy->countChat($messages, $model);
        } catch (TokenizerLoadException $e) {
            $this->warnings[] = "[TokenizerRegistry] '{$strategy->getStrategyName()}' failed for '{$model}': {$e->getMessage()}";

            return $this->fallback->countChat($messages, $model);
        }
    }

    public function supports(string $model): bool
    {
        return true; // always true because of the fallback
    }

    public function getStrategyName(): string
    {
        return 'registry';
    }

    // ─── Internal ───────────────────────────────────────────────────────────

    /**
     * Resolve the best available strategy for the given model.
     *
     * Resolution order:
     *   1. Exact match in factories
     *   2. Glob match (longest pattern wins, ties broken by registration order)
     *   3. Dynamic providers
     *   4. Global fallback
     */
    public function resolveFor(string $model): TokenizerInterface
    {
        // Step 1: exact match
        if (isset($this->factories[$model])) {
            return $this->instantiate($model, $this->factories[$model]);
        }

        // Step 2: glob match (longest pattern = most specific)
        $candidates = [];
        foreach (array_keys($this->factories) as $pattern) {
            if ($pattern !== '*' && fnmatch($pattern, $model)) {
                $candidates[$pattern] = strlen($pattern);
            }
        }

        if ($candidates !== []) {
            arsort($candidates); // longest pattern first
            foreach (array_keys($candidates) as $pattern) {
                $strategy = $this->tryInstantiate($model, $this->factories[$pattern]);
                if ($strategy !== null) {
                    return $strategy;
                }
            }
        }

        // Step 3: dynamic providers
        foreach ($this->providers as $provider) {
            $patterns = $provider->modelPatterns();
            $matches = array_filter($patterns, static fn($p) => fnmatch($p, $model));
            if ($matches === []) {
                continue;
            }

            try {
                $strategy = $provider->createFor($model);
                if ($strategy !== null) {
                    return $strategy;
                }
            } catch (TokenizerLoadException $e) {
                $this->warnings[] = "[TokenizerRegistry] Provider " . $provider::class .
                    " failed for '{$model}': {$e->getMessage()}";
            }
        }

        // Step 4: wildcard glob '*' fallback registered in catalog
        if (isset($this->factories['*'])) {
            $strategy = $this->tryInstantiate($model, $this->factories['*']);
            if ($strategy !== null) {
                return $strategy;
            }
        }

        // Step 5: hardcoded fallback
        return $this->fallback;
    }

    private function instantiate(string $cacheKey, Closure $factory): TokenizerInterface
    {
        if (!isset($this->resolved[$cacheKey])) {
            $this->resolved[$cacheKey] = $factory();
        }

        return $this->resolved[$cacheKey];
    }

    private function tryInstantiate(string $model, Closure $factory): ?TokenizerInterface
    {
        try {
            return $this->instantiate($model, $factory);
        } catch (TokenizerLoadException $e) {
            $this->warnings[] = "[TokenizerRegistry] Strategy unavailable for '{$model}': {$e->getMessage()}";
            return null;
        }
    }
}
