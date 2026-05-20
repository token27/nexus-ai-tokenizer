<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Vision;

use Token27\Tokenizer\Contract\ImageTokenEstimatorInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\ValueObject\TokenCount;

/**
 * Anthropic vision image token estimator for Claude models.
 *
 * Implements the official Anthropic formula.
 * Source: https://docs.anthropic.com/en/docs/build-with-claude/vision
 *
 * FORMULA:
 *   tokens = ceil((width × height) / 750)
 *
 * Image size limits (Anthropic):
 *   Max dimensions: 8000×8000 px
 *   Max file size:  5 MB
 *   If any dimension exceeds 1568 px, Anthropic scales it down automatically.
 *
 * @example
 *   $est = new AnthropicImageEstimator();
 *   echo $est->estimateImageTokens(1024, 1024, 'high', 'claude-sonnet-4-20250514')->count(); // 1400
 *   echo $est->estimateImageTokens(512,  512,  'auto', 'claude-sonnet-4-20250514')->count(); //  350
 */
final class AnthropicImageEstimator implements ImageTokenEstimatorInterface
{
    private const PIXELS_PER_TOKEN = 750;

    public function estimateImageTokens(
        int    $widthPx,
        int    $heightPx,
        string $detail,
        string $model,
    ): TokenCountInterface {
        $tokens = (int) ceil(($widthPx * $heightPx) / self::PIXELS_PER_TOKEN);

        return new TokenCount(
            count: max(1, $tokens),
            model: $model,
            strategy: 'image_estimator_anthropic',
            approximate: false,
        );
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'claude-');
    }
}
