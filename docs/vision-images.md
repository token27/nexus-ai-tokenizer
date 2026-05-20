# Multimodal Vision Estimation

Tokens aren't strictly relegated to text anymore. Multimodal inputs (passing images in Base64 or via URLs directly to LLMs) possess massive token charges based almost entirely on resolution dimensions.

Different algorithmic models use vastly differing logic mathematical blocks predicting the exact Token burn that the LLM processes. The library features estimating classes targeting the big three visually enabled endpoints.

## Executing Cost Scans

Rather than counting strings, leverage `estimateImage(width, height, detail_preset)` directly inside your pipeline mapping rules.

### OpenAI Logic

OpenAI limits the initial pixel bounding.

- **Low Detail**: Fixed 85 tokens. End of story.
- **High Detail**: Scales and crops to fit inside a 2048x2048 bounded box, then breaks the image into smaller 512x512 tiles, assigning 170 tokens for each generated tile, plus a static 85 token base modifier.

```php
use Token27\Tokenizer\Engine\TokenizerEngine;

$builder = TokenizerEngine::for('gpt-4o');

// High detail 1920x1080 
$hdCharge = $builder->estimateImage(1920, 1080, 'high');
echo $hdCharge->count(); // e.g., 1105 tokens

// Squeezed low cost resolution variant 
$lowCharge = $builder->estimateImage(1920, 1080, 'low');
echo $lowCharge->count(); // 85 tokens
```

### Anthropic Logic

Anthropic processes images purely through mathematical division (ceiling limits of the total width scaled against the height constrained linearly by the max tokens).

```php
// Claude automatically estimates using the internal Anthropic estimator. No high/low distinction exists.
$claudeCharge = TokenizerEngine::for('claude-3-opus-20240229')->estimateImage(1920, 1080);
echo $claudeCharge->count(); // 2765 tokens
```

### Gemini Logic

Gemini sets strict internal fixed boxes. If the shortest side ≤ 384px, it assigns exactly 258 tokens. Otherwise, it generates 768x768 tiles charging exactly 258 tokens per tile produced.

```php
$geminiCharge = TokenizerEngine::for('gemini-1.5-pro')->estimateImage(1920, 1080);
echo $geminiCharge->count(); // Generates distinct TokenCountInterface 
```

## Compiling Complete Payloads

When enforcing rigorous bounds, manually sum the Text logic counts with the Image estimations to guarantee perfect billing restrictions:

```php
// Cost 
$textTokens  = TokenizerEngine::for('gpt-4o')->count('Describe what you see.')->count();
$imageTokens = TokenizerEngine::for('gpt-4o')->estimateImage(1024, 768, 'high')->count();

// Perfect Limit Triggers
$totalConsumedContext = $textTokens + $imageTokens;
```

---
[❮ Previous: SentencePiece / Gemini](sentencepiece-gemini.md) | [Next: Custom Strategies ❯](custom-strategies.md)
