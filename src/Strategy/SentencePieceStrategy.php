<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Strategy;

use function count;

use Throwable;
use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Exception\TokenizerLoadException;
use Token27\Tokenizer\ValueObject\ChatTokenCount;
use Token27\Tokenizer\ValueObject\TokenCount;

/**
 * SentencePiece tokenizer for Gemini, LLaMA-2, T5, and similar models.
 *
 * Wraps textualization/sentencepiece, a PHP FFI binding to the Google SentencePiece C++ library.
 *
 * REQUIRED PACKAGES (both optional in composer.json):
 *   composer require textualization/sentencepiece
 *   System library: libsentencepiece.so (Linux) or sentencepiece.dll (Windows)
 *   Linux: sudo apt install libsentencepiece-dev
 *   macOS: brew install sentencepiece
 *
 * REQUIRED RUNTIME: PHP FFI extension must be enabled (ffi.enable=true in php.ini)
 *
 * MODEL FILE REQUIREMENT:
 *   You must provide the path to the .model file (SentencePiece binary format).
 *   For Gemini: download the Gemma tokenizer from Google.
 *     https://huggingface.co/google/gemma-2-2b/resolve/main/tokenizer.model
 *   For LLaMA-2: available in the Meta LLaMA-2 model release.
 *   For Mistral v1/v2: https://huggingface.co/mistralai/Mistral-7B-v0.1/resolve/main/tokenizer.model
 *
 * CHAT OVERHEAD:
 *   Gemini uses a proprietary conversational format with ~2–4 tokens overhead per message.
 *   The implementation uses a conservative 3-token/message + 3 estimate (same as OpenAI).
 *
 * @example
 *   $strategy = new SentencePieceStrategy('/opt/models/gemma/tokenizer.model');
 *   $count = $strategy->count('Hello world', 'gemini-1.5-pro');
 *   echo $count->count();  // exact token count via SentencePiece
 */
final class SentencePieceStrategy implements TokenizerInterface
{
    private mixed $sp = null;
    private bool $loaded = false;

    public function __construct(
        private readonly string $modelPath,
    ) {}

    public function count(string $text, string $model): TokenCountInterface
    {
        $this->ensureLoaded();

        $tokens = $this->sp->encode($text);
        $count = is_countable($tokens) ? count($tokens) : 0;

        return new TokenCount(
            count: $count,
            model: $model,
            strategy: $this->getStrategyName(),
            approximate: false,
            encoding: 'sentencepiece',
        );
    }

    /**
     * @param list<array{role?: string, content?: string}> $messages
     */
    public function countChat(array $messages, string $model): ChatTokenCountInterface
    {
        $this->ensureLoaded();

        $contentTokens = 0;

        foreach ($messages as $message) {
            $roleTokens = $this->sp->encode($message['role'] ?? '');
            $contentTokens += is_countable($roleTokens) ? count($roleTokens) : 0;
            $msgTokens = $this->sp->encode($message['content'] ?? '');
            $contentTokens += is_countable($msgTokens) ? count($msgTokens) : 0;
        }

        // Conservative chat overhead (Gemini exact overhead is not public)
        $overhead = count($messages) * 3 + 3;

        return new ChatTokenCount(
            count: $contentTokens + $overhead,
            contentTokens: $contentTokens,
            overheadTokens: $overhead,
            model: $model,
            strategy: $this->getStrategyName(),
            approximate: false,
            encoding: 'sentencepiece',
            messageCount: count($messages),
        );
    }

    public function supports(string $model): bool
    {
        return true;
    }

    public function getStrategyName(): string
    {
        return 'sentencepiece';
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        if (!class_exists(\Textualization\SentencePiece\SentencePiece::class)) {
            throw new TokenizerLoadException(
                "SentencePieceStrategy requires textualization/sentencepiece.\n" .
                "Install it with: composer require textualization/sentencepiece\n" .
                "Also requires: FFI PHP extension (ffi.enable=true in php.ini)\n" .
                "  Linux:  sudo apt install libsentencepiece-dev\n" .
                "  macOS:  brew install sentencepiece\n" .
                "  Windows: see https://packagist.org/packages/textualization/sentencepiece\n" .
                "Without it, the registry falls back to CharDivisionStrategy.",
            );
        }

        if (!file_exists($this->modelPath)) {
            throw new TokenizerLoadException(
                "SentencePiece model file not found at: {$this->modelPath}\n" .
                "Download the .model file from HuggingFace Hub for your model:\n" .
                "  Gemma:   https://huggingface.co/google/gemma-2-2b/resolve/main/tokenizer.model\n" .
                "  Mistral: https://huggingface.co/mistralai/Mistral-7B-v0.1/resolve/main/tokenizer.model",
            );
        }

        try {
            $this->sp = \Textualization\SentencePiece\SentencePiece::newFromFile($this->modelPath);
        } catch (Throwable $e) {
            throw new TokenizerLoadException(
                "Failed to load SentencePiece model from {$this->modelPath}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $this->loaded = true;
    }
}
