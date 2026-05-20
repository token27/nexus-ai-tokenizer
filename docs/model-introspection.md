# Model Introspection & Validation

Because `nexus-ai-tokenizer` bundles robust rules mapping models to algorithms, you will often need to programmatically determine if an arbitrary model identifier given by a user is officially mapped, or if the system will employ default fallbacks.

## Inspecting Known Models

If you wish to display all supported baseline model patterns, you can extract the registered catalog keys directly:

```php
use Token27\Tokenizer\Engine\TokenizerEngine;

// Retrieves the array of glob patterns registered out-of-the-box
// e.g., ['gpt-4o*', 'claude-*', 'gemini-*', 'deepseek-*', ...]
$patterns = TokenizerEngine::getKnownPatterns();
```

## Validating a Model Identifer

To determine if a specific text string (like `"gpt-4-turbo"` or `"opus-4-6"`) perfectly strikes an internal strategy, use `isKnownModel()`.

### Example 1: Passing a native model

```php
$isMapped = TokenizerEngine::isKnownModel('gpt-4o-2024-05-13');

if ($isMapped) {
    echo "Found! This will hit the `gpt-4o*` rule and use exact Tiktoken counts.";
}
```

### Example 2: Passing an unmapped proprietary model

```php
$isMapped = TokenizerEngine::isKnownModel('opus-4-6');

if (!$isMapped) {
    echo "Warning: No direct rule exists for 'opus-4-6'.";
    
    // NOTE: This does NOT crash the application natively!
    // If you call TokenizerEngine::for('opus-4-6') anyway, the internal registry
    // will safely catch it in the universal `*` fallback rule using CharDivisionStrategy.
}
```

## When to use Validation (Error Handling)

Although `TokenizerEngine::for('unknown')` will never throw an exception gracefully falling back to character-division, you might want strict mathematical enforcement in critical billing environments.

Here is an example of wrapping the validation for strict mathematical tracking:

```php
function getExactTokenCountOnly(string $text, string $model): int
{
    if (!TokenizerEngine::isKnownModel($model)) {
        throw new \InvalidArgumentException("Strict Mode: We cannot guarantee exact math for model: {$model}");
    }
    
    $counter = TokenizerEngine::for($model)->count($text);
    
    if ($counter->isApproximate()) {
        throw new \RuntimeException("Strict Mode: Native rule exists but returns an approximation (e.g. Claude).");
    }
    
    return $counter->count();
}
```

With this infrastructure, your ecosystem can confidently handle multi-provider input streams with zero ambiguous cost estimations.

---
[❮ Previous: Context Windows](context-window.md) | [Next: Exact vs Approximate ❯](exact-vs-approximate.md)
