<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Contract;

/**
 * Estimates tokens consumed by a single image in a multimodal prompt.
 *
 * Each provider uses a different formula:
 *   - OpenAI: tile-based (512×512 tiles, low=85 fixed, high=85+170×tiles)
 *   - Anthropic: (width × height) / 750
 *   - Gemini: tile-based (768×768 tiles, 258 tokens/tile)
 *
 * Built-in implementations:
 *   - OpenAIImageEstimator   — gpt-4o, gpt-4-vision, o1 models
 *   - AnthropicImageEstimator — claude-* models
 *   - GeminiImageEstimator   — gemini-* models
 *
 * @example
 *   $estimator = new OpenAIImageEstimator();
 *   $count = $estimator->estimateImageTokens(1024, 768, 'high', 'gpt-4o');
 *   echo $count->count(); // 765 tokens
 */
interface ImageTokenEstimatorInterface
{
    /**
     * Estimate the tokens consumed by an image.
     *
     * @param int    $widthPx  Image width in pixels.
     * @param int    $heightPx Image height in pixels.
     * @param string $detail   Detail level: 'low', 'high', or 'auto'.
     *                         'auto' is resolved to 'low' or 'high' by the estimator.
     * @param string $model    The model identifier used for the vision request.
     *
     * @return TokenCountInterface Token estimate with strategy='image_estimator'.
     */
    public function estimateImageTokens(
        int    $widthPx,
        int    $heightPx,
        string $detail,
        string $model,
    ): TokenCountInterface;

    /**
     * Returns true if this estimator handles the given model.
     *
     * @param string $model The model identifier.
     */
    public function supports(string $model): bool;
}
