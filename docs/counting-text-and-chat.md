# Text & Chat Token Counting

The library maps different prompting methodologies (flat strings vs. message objects) to precise token measurements.

## Raw Text Token Counting

To count variables or plain string payload segments, use `$engine->count()`:

```php
use Token27\Tokenizer\Engine\TokenizerEngine;

$builder = TokenizerEngine::for('claude-3-opus-20240229');
$count = $builder->count('Lorem ipsum dolor sit amet.');

echo $count->count(); // The token representation length
echo $count->isApproximate(); // True (Claude is approximated via cl100k_base)
```

The resulting `TokenCountInterface` provides several getters:

- `count()`: The integer token quantity.
- `model()`: The model requested (e.g., `claude-3-opus-20240229`).
- `strategy()`: The internal strategy class matching the model.
- `isApproximate()`: A boolean indicating if this is exact or estimated.

## Chat Conversational Format

Using simple text string counts for chat arrays (`[['role' => 'user', 'content' => 'Hi']]`) produces dangerous underestimations. Every provider applies formatting characters to differentiate roles.

For example, OpenAI injects tokens using `ChatML` sequences (`<|im_start|>user\nHi<|im_end|>\n`). DeepSeek applies EOS IDs.

To accurately score a multi-turn array of messages, use `countChat()`:

```php
$messages = [
    ['role' => 'system', 'content' => 'You are a precise analyzer.'],
    ['role' => 'user', 'content' => 'What is 2+2?'],
];

// Resolves exact Tiktoken algorithm rules
$chatCount = TokenizerEngine::for('gpt-4o')->countChat($messages);

// Number of tokens produced strictly by the words ('You are a precise analyzer' etc)
echo "Content: " . $chatCount->contentTokens() . "\n";

// Number of tokens consumed by the provider's message wrapping and structure
echo "Overhead: " . $chatCount->overheadTokens() . "\n";

// Total billing quantity
echo "Total: " . $chatCount->count() . "\n";
```

### Breakdown of OpenAI Overhead

When scoring for `gpt-3.5-turbo` or newer:

- 3 tokens added per message.
- 3 tokens added universally to the entire prompt to prime the assistant's turn.

If you have 10 messages, you pay approximately **33 overhead tokens** completely unrelated to the length of your text content.

## Batch Processing

If you need to analyze multiple disparate strings simultaneously (e.g., verifying if ten source documents are individually small enough), use `countBatch()`:

```php
$documents = [
    'Document One data...',
    'Document Two data...',
    'Document Three data...'
];

$counts = TokenizerEngine::for('o1')->countBatch($documents);

foreach ($counts as $index => $tokenCount) {
    echo "Document $index costs " . $tokenCount->count() . " tokens.\n";
}
```

---
[❮ Previous: Installation & Setup](installation.md) | [Next: Context Windows ❯](context-window.md)
