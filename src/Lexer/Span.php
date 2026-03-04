<?php

declare(strict_types=1);

namespace PslToml\Lexer;

use Psl\Math;
use Psl\Range;
use Psl\Str;
use Psl\Type;

/**
 * A half-open byte range {@code [start, end)} within the original source string.
 *
 * Offsets are zero-based and measured in bytes, not Unicode code points.
 * The range is considered **half-open**: {@see self::$start} is inclusive,
 * {@see self::$end} is exclusive — so {@code end - start} always equals
 * the byte length of the covered lexeme.
 *
 * {@see self::$start} must always be less than or equal to {@see self::$end}.
 * A span where both are equal represents a zero-length (empty) range, which
 * is used exclusively for the {@see TokenType::Eof} sentinel.
 *
 * @see Token
 */
final readonly class Span
{
    /**
     * @param  int<0, max>  $start  Inclusive start offset, in bytes.
     * @param  int<0, max>  $end    Exclusive end offset, in bytes.
     *
     * @throws \Psl\Type\Exception\AssertException if {@code $start} or {@code $end} are negative,
     *                                             or if {@code $end} is less than {@code $start}.
     *
     * @mago-ignore analyzer:redundant-type-comparison
     */
    public function __construct(
        public readonly int $start,
        public readonly int $end,
    ) {
        Type\uint()->assert($start);
        Type\uint()->assert($end);
        Type\uint()->assert($end - $start);
    }

    /**
     * Creates a zero-length span at the given offset.
     *
     * Used for synthetic tokens such as {@see TokenType::Eof}, which occupy
     * no bytes in the source but still need a position for error reporting.
     *
     * @param  int<0, max>  $offset
     *
     * @throws \Psl\Type\Exception\AssertException if {@code $offset} is negative.
     */
    public static function empty(int $offset): self
    {
        return new self($offset, $offset);
    }

    /**
     * Creates a span that covers the union of {@code $this} and {@code $other}.
     *
     * The two spans do not need to be adjacent or overlapping; the result
     * simply stretches from the earliest start to the latest end.
     * Useful when the parser merges multiple tokens into a single AST node.
     *
     * @mago-ignore analyzer:unhandled-thrown-type
     */
    public function merge(self $other): self
    {
        return new self(Math\minva($this->start, $other->start), Math\maxva($this->end, $other->end));
    }

    /**
     * Returns the byte length of the range ({@code end - start}).
     *
     * @return int<0, max>
     * @mago-ignore analyzer:invalid-return-statement
     */
    public function length(): int
    {
        return $this->end - $this->start;
    }

    /**
     * Returns {@code true} when the span covers zero bytes.
     *
     * Only expected for {@see TokenType::Eof} tokens.
     */
    public function isEmpty(): bool
    {
        return $this->start === $this->end;
    }

    /**
     * Returns {@code true} when this span completely contains {@code $other}.
     */
    public function contains(self $other): bool
    {
        return $this->start <= $other->start && $other->end <= $this->end;
    }

    /**
     * Slices {@code $source} and returns the exact bytes covered by this span.
     *
     * @param  non-empty-string  $source  The original TOML source document.
     * @return string                     The raw lexeme bytes.
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException if the span exceeds the bounds of {@code $source}.
     */
    public function slice(string $source): string
    {
        return Str\slice($source, $this->start, $this->length());
    }

    /**
     * Converts the span's start offset to a human-readable {@code line:column}
     * pair by scanning {@code $source} for newline characters.
     *
     * Lines and columns are both **1-based** to match the convention used in
     * most editors and diagnostic tools.
     *
     * This operation is intentionally not cached — it is expected to be called
     * only when building a {@see \PslToml\Exception\ParseException}, not in the
     * hot path of the lexer or parser.
     *
     * @param  non-empty-string  $source  The original TOML source document.
     * @return array{line: int<1, max>, column: int<1, max>}
     *
     * @mago-ignore analyzer:unhandled-thrown-type
     */
    public function toLineColumn(string $source): array
    {
        $line = 1;
        $column = 1;

        foreach (Range\between(0, $this->start) as $i) {
            if ($source[$i] === "\n") {
                $line++;
                $column = 1;
                continue;
            }
            $column++;
        }

        return ['line' => $line, 'column' => $column];
    }

    /**
     * Returns a compact debug representation, e.g. {@code Span(7..13)}.
     */
    public function __toString(): string
    {
        return "Span({$this->start}..{$this->end})";
    }
}
