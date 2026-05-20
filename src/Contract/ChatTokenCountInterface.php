<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Contract;

/**
 * Result object returned by countChat() operations.
 *
 * Extends TokenCountInterface with chat-specific breakdown:
 * content tokens (message bodies) vs overhead tokens (provider format markers).
 *
 * @example
 *   $result = TokenizerEngine::for('gpt-4o')->countChat($messages);
 *   echo $result->count();          // total tokens
 *   echo $result->contentTokens();  // tokens from message content only
 *   echo $result->overheadTokens(); // tokens from ChatML / role markers
 *   echo $result->messageCount();   // number of messages counted
 */
interface ChatTokenCountInterface extends TokenCountInterface
{
    /** Tokens from message content only (roles + bodies), excluding format overhead. */
    public function contentTokens(): int;

    /** Tokens added by the provider's chat format (ChatML, role markers, BOS/EOS, etc.). */
    public function overheadTokens(): int;

    /** Number of messages that were counted. */
    public function messageCount(): int;
}
