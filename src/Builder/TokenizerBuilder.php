<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Builder;

use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\ImageTokenEstimatorInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\Vision\AnthropicImageEstimator;
use Token27\Tokenizer\Vision\GeminiImageEstimator;
use Token27\Tokenizer\Vision\OpenAIImageEstimator;

/**
 * Fluent interface for tokenizing text for a specific model.
 *
 * Created by TokenizerEngine::for(model) — never instantiate directly.
 * All methods on this class are terminal operations that return results.
 *
 * @example
 *   $builder = TokenizerEngine::for('gpt-4o');
 *
 *   // Count plain text
 *   $count = $builder->count('Hello world');
 *   echo $count->count();   // 2
 *   echo $count->format();  // "2 tokens (tiktoken / o200k_base)"
 *
 *   // Count a conversation
 *   $count = $builder->countChat([
 *       ['role' => 'system',    'content' => 'You are helpful.'],
 *       ['role' => 'user',      'content' => 'Explain BPE.'],
 *   ]);
 *
 *   // Count multiple texts (returns array of TokenCountInterface)
 *   $counts = $builder->countBatch(['Hello', 'world', 'foo bar']);
 *
 *   // Estimate image tokens
 *   $imgCount = $builder->estimateImage(1024, 768, 'high');
 *
 *   // Check context window
 *   $count = $builder->count($prompt);
 *   if (!$count->isWithinContextWindow(128_000)) { throw new \RuntimeException('Too long'); }
 */
final class TokenizerBuilder
{
    /** @var list<ImageTokenEstimatorInterface> */
    private static array $imageEstimators = [];

    public function __construct(
        private readonly string $model,
        private readonly TokenizerRegistry $registry,
    ) {}

    /**
     * Count tokens in a plain text string.
     *
     * @param string $text The text to tokenize.
     */
    public function count(string $text): TokenCountInterface
    {
        return $this->registry->count($text, $this->model);
    }

    /**
     * Count tokens in a full chat conversation, including provider-specific overhead.
     *
     * @param list<array{role?: string, content?: string}> $messages Ordered messages.
     */
    public function countChat(array $messages): ChatTokenCountInterface
    {
        return $this->registry->countChat($messages, $this->model);
    }

    /**
     * Count tokens in multiple texts, returning one result per text.
     *
     * @param list<string> $texts Texts to count.
     *
     * @return list<TokenCountInterface>
     */
    public function countBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $text) {
            $results[] = $this->registry->count($text, $this->model);
        }

        return $results;
    }

    /**
     * Estimate tokens consumed by an image in a multimodal prompt.
     *
     * Automatically selects the correct formula based on the model:
     *   gpt-4o / o1 / o3 → OpenAI tile formula
     *   claude-*         → Anthropic formula  (width × height) / 750
     *   gemini-*         → Gemini tile formula
     *
     * @param int    $widthPx  Image width in pixels.
     * @param int    $heightPx Image height in pixels.
     * @param string $detail   'low', 'high', or 'auto'.
     */
    public function estimateImage(int $widthPx, int $heightPx, string $detail = 'auto'): TokenCountInterface
    {
        $estimators = $this->getImageEstimators();

        foreach ($estimators as $estimator) {
            if ($estimator->supports($this->model)) {
                return $estimator->estimateImageTokens($widthPx, $heightPx, $detail, $this->model);
            }
        }

        // Fallback: use OpenAI formula as conservative estimate
        return (new OpenAIImageEstimator())->estimateImageTokens($widthPx, $heightPx, $detail, $this->model);
    }

    /**
     * Return the resolved strategy for this model (for inspection / custom use).
     */
    public function getStrategy(): TokenizerInterface
    {
        return $this->registry->resolveFor($this->model);
    }

    /**
     * Return the model this builder is bound to.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * True if the registry has a non-fallback strategy for this model.
     */
    public function supportsModel(): bool
    {
        $strategy = $this->registry->resolveFor($this->model);

        return $strategy->getStrategyName() !== 'char_division';
    }

    /** @return list<ImageTokenEstimatorInterface> */
    private function getImageEstimators(): array
    {
        if (self::$imageEstimators === []) {
            self::$imageEstimators = [
                new OpenAIImageEstimator(),
                new AnthropicImageEstimator(),
                new GeminiImageEstimator(),
            ];
        }

        return self::$imageEstimators;
    }
}
