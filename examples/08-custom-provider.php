<?php

declare(strict_types=1);

/**
 * Example 08 — Registering a dynamic provider.
 *
 * A TokenizerProviderInterface is useful when you need to load strategies
 * dynamically at runtime — for example, from a database, config file, or
 * remote registry — without knowing the exact model patterns at startup.
 *
 * Providers are queried after all static registrations fail.
 *
 * Run: php examples/08-custom-provider.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Contract\TokenizerProviderInterface;
use Token27\Tokenizer\Engine\TokenizerEngine;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;
use Token27\Tokenizer\Strategy\TiktokenStrategy;

/**
 * A provider that routes internal company models to appropriate strategies
 * based on a runtime registry (simulated here as an array).
 */
final class CompanyModelProvider implements TokenizerProviderInterface
{
    /** @var array<string, string> model-pattern → encoding */
    private array $registry = [
        'acme-chat-*'   => 'cl100k_base',
        'acme-code-*'   => 'o200k_base',
        'acme-embed-*'  => 'cl100k_base',
    ];

    public function modelPatterns(): array
    {
        return array_keys($this->registry);
    }

    public function createFor(string $model): ?TokenizerInterface
    {
        foreach ($this->registry as $pattern => $encoding) {
            if (fnmatch($pattern, $model)) {
                return new TiktokenStrategy($encoding);
            }
        }

        return null; // Fall through to next provider or fallback
    }
}

// ── Register the provider ───────────────────────────────────────────────────

$text = 'How many tokens does this sentence consume?';

$engine = TokenizerEngine::withProvider(new CompanyModelProvider());

$models = ['acme-chat-v2', 'acme-code-v3', 'acme-embed-v1', 'unknown-model'];

echo "Text: \"{$text}\"\n\n";
echo str_pad('Model', 20) . str_pad('Tokens', 10) . "Strategy\n";
echo str_repeat('-', 40) . "\n";

foreach ($models as $model) {
    $count = $engine->make($model)->count($text);
    echo str_pad($model, 20) . str_pad((string) $count->count(), 10) . $count->strategy() . "\n";
}

echo "\n--- Provider + custom strategy together ---\n";

// You can combine a provider with additional static registrations
$combinedEngine = TokenizerEngine::withProvider(new CompanyModelProvider())
    ->andStrategy('legacy-model-v1', new CharDivisionStrategy());

echo "legacy-model-v1: " . $combinedEngine->make('legacy-model-v1')->count($text)->strategy() . "\n";
echo "acme-chat-v2:    " . $combinedEngine->make('acme-chat-v2')->count($text)->strategy() . "\n";
