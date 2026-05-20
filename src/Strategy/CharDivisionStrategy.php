<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Strategy;

use function count;

use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\ValueObject\ChatTokenCount;
use Token27\Tokenizer\ValueObject\TokenCount;

/**
 * Fallback tokenizer using the character-division heuristic: ceil(mb_strlen / 4).
 *
 * This is NOT a real tokenizer. It is intentionally imprecise:
 *   - English prose: ±15% error
 *   - Code / markdown: ±40% error
 *   - Non-ASCII (CJK, Arabic, etc.): ±60% error (multibyte chars inflate the count)
 *
 * It is always available — no external dependencies, no files, no network.
 * The registry uses it as a universal fallback when no better strategy is installed.
 *
 * IMPORTANT: results are always marked as approximate (isApproximate() === true).
 * If you see this strategy in production logs for models that should use tiktoken,
 * install the missing optional package listed in the log warning.
 *
 * @example
 *   $strategy = new CharDivisionStrategy();
 *   $count = $strategy->count('Hello world', 'any-model');
 *   // ~2–3 tokens (very rough estimate)
 *   $count->isApproximate(); // true
 *   $count->strategy();      // 'char_division'
 */
final class CharDivisionStrategy implements TokenizerInterface
{
    public function count(string $text, string $model): TokenCountInterface
    {
        $tokenCount = (int) ceil(mb_strlen($text, 'UTF-8') / 4);

        return new TokenCount(
            count: max(0, $tokenCount),
            model: $model,
            strategy: $this->getStrategyName(),
            approximate: true,
        );
    }

    /**
     * @param list<array{role?: string, content?: string}> $messages
     */
    public function countChat(array $messages, string $model): ChatTokenCountInterface
    {
        $contentTokens = 0;

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';
            $content = $message['content'] ?? '';
            $text = $role . ': ' . $content;
            $contentTokens += (int) ceil(mb_strlen($text, 'UTF-8') / 4);
        }

        // Conservative overhead estimate: 3 tokens/message + 3 priming
        $overhead = count($messages) * 3 + 3;

        return new ChatTokenCount(
            count: $contentTokens + $overhead,
            contentTokens: $contentTokens,
            overheadTokens: $overhead,
            model: $model,
            strategy: $this->getStrategyName(),
            approximate: true,
            messageCount: count($messages),
        );
    }

    public function supports(string $model): bool
    {
        return true; // universal fallback — accepts any model
    }

    public function getStrategyName(): string
    {
        return 'char_division';
    }
}
