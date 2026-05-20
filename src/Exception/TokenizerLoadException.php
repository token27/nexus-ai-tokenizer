<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Exception;

use RuntimeException;

/**
 * Thrown when a strategy cannot initialize because a required dependency is missing.
 *
 * The message MUST include an actionable fix. Example:
 *   "TiktokenStrategy requires danny50610/bpe-tokeniser.
 *    Install it with: composer require danny50610/bpe-tokeniser
 *    Without it, the registry falls back to CharDivisionStrategy (±40% error)."
 *
 * The registry catches this exception and logs a warning before falling back
 * to CharDivisionStrategy, so the application continues to function.
 */
final class TokenizerLoadException extends RuntimeException {}
