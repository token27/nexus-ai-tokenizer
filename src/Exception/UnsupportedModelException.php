<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Exception;

use RuntimeException;

/**
 * Thrown when a strategy is asked to tokenize a model it does not support.
 *
 * The TokenizerRegistry catches this and tries the next candidate.
 * It reaches the caller only when no strategy (including the fallback) supports the model,
 * which is currently impossible because CharDivisionStrategy::supports() always returns true.
 */
final class UnsupportedModelException extends RuntimeException {}
