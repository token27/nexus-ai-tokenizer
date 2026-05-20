# SentencePiece & Gemini Exact Tokenization

Models created by Google, specifically the Gemini line and the open-source Gemma family, utilize the **SentencePiece** tokenization algorithm. SentencePiece reads the raw model directly using a compiled binary `.model` format.

Since this algorithm is extremely complex and stored dynamically, pure PHP string mappings often fall incredibly short.

## Requirements for Exact Gemini Tokens

To provide true exact tokens, the library leverages FFI (Foreign Function Interface) to hook directly into the system's compiled C++ SentencePiece library.

To unlock this:

1. **C++ System Dependency**: You must have `libsentencepiece-dev` installed on your server (e.g. `sudo apt install libsentencepiece-dev`).
2. **Extensions**: Ensure `ffi.enable = "true"` is set in your PHP configuration.
3. **Adapter Package**: Install `composer require textualization/sentencepiece`.
4. **Vocabulary Asset**: Download the exact Gemini/Gemma `tokenizer.model` file from HuggingFace ([Link to Gemma-2 tokenizer model](https://huggingface.co/google/gemma-2-2b/resolve/main/tokenizer.model)).

## Hooking the SentencePiece Strategy

Unlike OpenAI zero-config setups, since SentencePiece forces you to store a `.model` binary, you must register a custom `SentencePieceStrategy` dynamically:

```php
use Token27\Tokenizer\Engine\TokenizerEngine;
use Token27\Tokenizer\Strategy\SentencePieceStrategy;

$strategy = new SentencePieceStrategy('/absolute/path/to/tokenizer.model');

$engine = TokenizerEngine::withCustomStrategy('gemini-*', $strategy)
    ->andCustomStrategy('gemma-*', $strategy); // Re-use the same loaded binary!

// Execute exact token counting.
$count = $engine->make('gemini-1.5-pro')->count('The context window handles this accurately.');

echo $count->isApproximate(); // false! Exact mathematically accurate counts achieved!
echo $count->strategy(); // sentence_piece
```

## The CharDivision Fallback

If you attempt to count a `gemini-*` sequence via `TokenizerEngine::for('gemini-1.5-pro')` using the default zero-config catalog, it will immediately drop to the `CharDivisionStrategy`.

This strategy just crudely divides the string length by 4. It holds a very high error rate (±40%) and acts simply as an absolute last resort safety net ensuring your code doesn't crash on unmapped proprietary LLMs.

Always configure the FFI SentencePiece Strategy for serious production constraints utilizing Gemini contexts.

---
[❮ Previous: HuggingFace BPE](huggingface-bpe.md) | [Next: Vision & Images ❯](vision-images.md)
