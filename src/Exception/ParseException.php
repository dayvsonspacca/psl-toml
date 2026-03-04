<?php

declare(strict_types=1);

namespace PslToml\Exception;

use Psl\Str;
use PslToml\Lexer\Span;

/**
 * Thrown when the lexer or parser encounters invalid TOML input.
 *
 * The exception carries the original source document and the {@see Span} of
 * the offending token, allowing it to report a precise line and column in its
 * message without requiring the caller to perform any conversion.
 */
final class ParseException extends \RuntimeException
{
    /**
     * @param  non-empty-string  $source
     */
    public function __construct(
        private readonly string $source,
        public readonly Span $span,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        ['line' => $line, 'column' => $column] = $span->toLineColumn($source);

        parent::__construct(Str\format('%s at line %d, column %d', $reason, $line, $column), 0, $previous);
    }

    /**
     * Returns the slice of the source document that caused the error.
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException if the span exceeds the bounds of the source.
     */
    public function lexeme(): string
    {
        return $this->span->slice($this->source);
    }
}
