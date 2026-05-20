<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Vision;

use Token27\Tokenizer\Contract\ImageTokenEstimatorInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\ValueObject\TokenCount;

/**
 * OpenAI vision image token estimator.
 *
 * Implements the official OpenAI formula for gpt-4o, gpt-4-vision-preview, and o1 models.
 * Source: https://platform.openai.com/docs/guides/vision/calculating-costs
 *
 * FORMULA:
 *   low detail:  85 tokens (fixed, regardless of size)
 *   high detail:
 *     1. Scale image so longest side ≤ 2048px (maintaining aspect ratio)
 *     2. Scale image so shortest side ≤ 768px (maintaining aspect ratio)
 *     3. tiles = ceil(width / 512) × ceil(height / 512)
 *     4. tokens = 85 + (170 × tiles)
 *   auto: resolved to low if both dimensions ≤ 512, else high
 *
 * @example
 *   $est = new OpenAIImageEstimator();
 *   echo $est->estimateImageTokens(1024, 768, 'high', 'gpt-4o')->count(); // 765
 *   echo $est->estimateImageTokens(512,  512,  'low',  'gpt-4o')->count(); //  85
 */
final class OpenAIImageEstimator implements ImageTokenEstimatorInterface
{
    private const BASE_TOKENS = 85;
    private const TILE_TOKENS = 170;
    private const TILE_SIZE = 512;
    private const MAX_LONG_SIDE = 2048;
    private const MAX_SHORT_SIDE = 768;
    private const AUTO_THRESHOLD = 512;

    public function estimateImageTokens(
        int $widthPx,
        int $heightPx,
        string $detail,
        string $model,
    ): TokenCountInterface {
        $tokens = match ($this->resolveDetail($widthPx, $heightPx, $detail)) {
            'low' => self::BASE_TOKENS,
            'high' => $this->highDetailTokens($widthPx, $heightPx),
            default => self::BASE_TOKENS,
        };

        return new TokenCount(
            count: $tokens,
            model: $model,
            strategy: 'image_estimator_openai',
            approximate: false,
            encoding: $detail,
        );
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'gpt-4')
            || str_starts_with($model, 'gpt-4o')
            || str_starts_with($model, 'o1')
            || str_starts_with($model, 'o3');
    }

    private function resolveDetail(int $width, int $height, string $detail): string
    {
        if ($detail === 'low') {
            return 'low';
        }

        if ($detail === 'auto') {
            return ($width <= self::AUTO_THRESHOLD && $height <= self::AUTO_THRESHOLD)
                ? 'low'
                : 'high';
        }

        return 'high';
    }

    private function highDetailTokens(int $width, int $height): int
    {
        // Step 1: scale so longest side ≤ 2048
        [$width, $height] = $this->scaleToFit($width, $height, self::MAX_LONG_SIDE);

        // Step 2: scale so shortest side ≤ 768
        $short = min($width, $height);
        if ($short > self::MAX_SHORT_SIDE) {
            $ratio = self::MAX_SHORT_SIDE / $short;
            $width = (int) ($width * $ratio);
            $height = (int) ($height * $ratio);
        }

        // Step 3: tile count
        $tiles = (int) ceil($width / self::TILE_SIZE) * (int) ceil($height / self::TILE_SIZE);

        return self::BASE_TOKENS + self::TILE_TOKENS * $tiles;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function scaleToFit(int $width, int $height, int $maxSide): array
    {
        $long = max($width, $height);
        if ($long <= $maxSide) {
            return [$width, $height];
        }

        $ratio = $maxSide / $long;
        return [(int) ($width * $ratio), (int) ($height * $ratio)];
    }
}
