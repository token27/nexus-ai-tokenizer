<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Vision;

use Token27\Tokenizer\Contract\ImageTokenEstimatorInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\ValueObject\TokenCount;

/**
 * Google Gemini vision image token estimator.
 *
 * Implements the tile-based formula for Gemini 1.5+ models.
 * Source: https://ai.google.dev/gemini-api/docs/tokens
 *
 * FORMULA:
 *   If shortest side ≤ 384px: 258 tokens (fixed)
 *   Otherwise:
 *     - Image is conceptually divided into 768×768 px tiles
 *     - tilesW = ceil(width  / 768)
 *     - tilesH = ceil(height / 768)
 *     - tokens = tilesW × tilesH × 258
 *
 * @example
 *   $est = new GeminiImageEstimator();
 *   echo $est->estimateImageTokens(256, 256, 'auto', 'gemini-1.5-pro')->count(); // 258
 *   echo $est->estimateImageTokens(1024, 768, 'auto', 'gemini-1.5-pro')->count(); // 516
 */
final class GeminiImageEstimator implements ImageTokenEstimatorInterface
{
    private const TOKENS_PER_TILE  = 258;
    private const TILE_SIZE        = 768;
    private const SMALL_IMAGE_SIDE = 384;

    public function estimateImageTokens(
        int    $widthPx,
        int    $heightPx,
        string $detail,
        string $model,
    ): TokenCountInterface {
        $shortest = min($widthPx, $heightPx);

        $tokens = $shortest <= self::SMALL_IMAGE_SIDE
            ? self::TOKENS_PER_TILE
            : $this->tiledTokens($widthPx, $heightPx);

        return new TokenCount(
            count: $tokens,
            model: $model,
            strategy: 'image_estimator_gemini',
            approximate: false,
        );
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'gemini-')
            || str_starts_with($model, 'gemma-');
    }

    private function tiledTokens(int $width, int $height): int
    {
        $tilesW = (int) ceil($width  / self::TILE_SIZE);
        $tilesH = (int) ceil($height / self::TILE_SIZE);

        return $tilesW * $tilesH * self::TOKENS_PER_TILE;
    }
}
