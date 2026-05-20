# Custom Strategies & Providers

The library's architecture heavily decouples the concept of Models from Tokenizer algorithms. This makes it trivial to override internal catalogs or define internal company-exclusive AI models.

## Custom Tokenizer Strategy

If you have a proprietary AI model or want to write a completely isolated tokenizer layer, implement `TokenizerInterface`.

```php
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\ValueObject\TokenCount;

final class WordCountTokenizer implements TokenizerInterface
{
    public function count(string $text, string $model): TokenCountInterface
    {
        $words = $text === '' ? 0 : count(preg_split('/\s+/', trim($text)));

        return new TokenCount(
            count:       $words,
            model:       $model,
            strategy:    $this->getStrategyName(),
            approximate: true, 
        );
    }
    
    // ... Implement countChat() ...

    public function supports(string $model): bool
    {
        return true; 
    }

    public function getStrategyName(): string
    {
        return 'word_count';
    }
}
```

You can then inject it directly into the static Engine using wildcard matching:

```php
use Token27\Tokenizer\Engine\TokenizerEngine;

// Will override any internal 'gpt-*' if matching
$engine = TokenizerEngine::withCustomStrategy('internal-company-model-*', new WordCountTokenizer());

$count = $engine->make('internal-company-model-v2')->count('Hi there');
```

## Custom Strategy Providers

For larger operations, you likely don't want to statically map rules using `withCustomStrategy()` ten times.

Instead, build a `TokenizerProviderInterface` capable of dynamically routing and spinning up Strategy objects:

```php
use Token27\Tokenizer\Contract\TokenizerProviderInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Strategy\TiktokenStrategy;

final class DatabaseModelProvider implements TokenizerProviderInterface
{
    public function modelPatterns(): array
    {
        // Indicate to the engine which model names this provider can handle
        return ['db-model-*'];
    }

    public function createFor(string $model): ?TokenizerInterface
    {
        // Query database, fetch internal model algorithm setting...
        return new TiktokenStrategy('o200k_base');
    }
}
```

Mount it once:

```php
$engine = TokenizerEngine::withProvider(new DatabaseModelProvider());
```

---
[❮ Previous: Vision & Images](vision-images.md) | [Next: Testing ❯](testing.md)
