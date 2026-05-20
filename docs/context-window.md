# Context Windows

LLMs have hard bounds on input capacity. Tracking and respecting context windows allows your application to gracefully shift to cheaper models or perform iterative truncation rather than crashing abruptly with a `400 Bad Request` API error.

The `TokenCount` and `ChatTokenCount` implementations offer expressive context-checking primitives.

## Checking Capacity

After counting your text, directly check if it survives your application's defined limits.

```php
use Token27\Tokenizer\Engine\TokenizerEngine;

$count = TokenizerEngine::for('gpt-4-turbo')->count($massiveDocument);
$contextLimit = 128_000;

if (!$count->isWithinContextWindow($contextLimit)) {
    throw new \Exception("Document is too large by " . ($count->count() - $contextLimit) . " tokens!");
}

echo "You have " . $count->remainingTokens($contextLimit) . " tokens remaining.";
```

### Relative Load Checks

You can programmatically verify what percentage of the budget a message consumes. This is helpful if you want to leave an allocated 20% margin for the AI's actual generated output.

```php
$pct = $count->percentageOf($contextLimit);

if ($pct > 0.8) {
    echo "Warning: Payload is highly dense. You are consuming " . ($pct * 100) . "% of the context.";
}
```

## Handling Truncation

Context helpers enable precise iterative truncation processes (e.g., truncating by sentences or paragraphs until it squarely fits underneath the context minus the system instructions).

```php
// Goal: 16k context, reserve 1000 for output, 50 for system prompt.
$budget = 16_385 - 1000 - 50;

$builder = TokenizerEngine::for('gpt-3.5-turbo');

$sentences = explode('. ', $hugeDocument);
$accumulated = '';

foreach ($sentences as $sentence) {
    // Propose adding the next sentence
    $candidate = ($accumulated === '' ? '' : ' ') . $sentence . '.';
    $proposedCount = $builder->count($accumulated . $candidate)->count();

    // Abort if it blows the budget
    if ($proposedCount > $budget) {
        break;
    }

    $accumulated .= $candidate;
}

echo "Successfully extracted a truncated document totaling " . $builder->count($accumulated)->count() . " tokens.";
```

---
[❮ Previous: Text & Chat Token Counting](counting-text-and-chat.md) | [Next: Exact vs Approximate ❯](exact-vs-approximate.md)
