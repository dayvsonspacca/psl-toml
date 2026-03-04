<?php

declare(strict_types=1);

namespace PslToml\Lexer;

use Psl\Iter;

/**
 * An immutable value produced by the lexer representing a single unit of TOML source.
 *
 * A token is the combination of three things:
 *  - **what** it is  — {@see self::$type}, the syntactic category;
 *  - **what it says** — {@see self::$lexeme}, the raw bytes from the source;
 *  - **where it is**  — {@see self::$span}, its position within the document.
 *
 * Tokens are pure values: they carry no behaviour, hold no references to the
 * source string, and are never mutated after construction.
 *
 * @see TokenType
 * @see Span
 */
final readonly class Token
{
    public function __construct(
        /** The syntactic category of this token. */
        public readonly TokenType $type,

        /**
         * The exact bytes from the source document that this token covers.
         *
         * For punctuation tokens the lexeme is always the fixed symbol
         * (e.g. {@code "="} for {@see TokenType::Equals}).
         * For literal tokens (strings, numbers, keys, date/times) the lexeme
         * is the raw, uninterpreted source text — conversion to a typed PHP
         * value is the responsibility of the parser, not the lexer.
         * For {@see TokenType::Eof} the lexeme is always an empty string.
         */
        public readonly string $lexeme,

        /** The half-open byte range {@code [start, end)} within the source. */
        public readonly Span $span,
    ) {}

    /**
     * Creates the {@see TokenType::Eof} sentinel token.
     *
     * The lexeme is empty and the span is zero-length, anchored at the byte
     * immediately past the last character of the source.
     *
     * @param  int<0, max>  $offset  Byte offset of end-of-file.
     * @throws \Psl\Type\Exception\AssertException if {@code $offset} is negative.
     */
    public static function eof(int $offset): self
    {
        return new self(TokenType::Eof, '', Span::empty($offset));
    }

    /**
     * Creates a {@see TokenType::Invalid} token for an unrecognised byte.
     *
     * The lexeme holds the offending character so that error messages can
     * quote it verbatim.
     *
     * @param  non-empty-string  $character  The unrecognised byte or character.
     * @param  Span              $span       Its location in the source.
     */
    public static function invalid(string $character, Span $span): self
    {
        return new self(TokenType::Invalid, $character, $span);
    }

    /**
     * Returns {@code true} when this token's type matches {@code $type}.
     */
    public function is(TokenType $type): bool
    {
        return $this->type === $type;
    }

    /**
     * Returns {@code true} when this token's type matches any of {@code $types}.
     */
    public function isAny(TokenType ...$types): bool
    {
        return Iter\contains($types, $this->type);
    }

    /**
     * Returns {@code true} for trivia tokens (whitespace, newlines, comments).
     *
     * @see TokenType::isTrivia()
     */
    public function isTrivia(): bool
    {
        return $this->type->isTrivia();
    }

    /**
     * Returns {@code true} if this token can appear as a key.
     *
     * @see TokenType::isKey()
     */
    public function isKey(): bool
    {
        return $this->type->isKey();
    }

    /**
     * Returns {@code true} if this token can appear as a scalar value.
     *
     * @see TokenType::isValue()
     */
    public function isValue(): bool
    {
        return $this->type->isValue();
    }

    /**
     * Returns {@code true} when this is the end-of-file sentinel.
     */
    public function isEof(): bool
    {
        return $this->type === TokenType::Eof;
    }

    /**
     * Returns a compact debug representation, e.g. {@code Token(Integer, "1_000", Span(8..13))}.
     */
    public function __toString(): string
    {
        $label = $this->type->name;
        $lexeme = $this->lexeme !== '' ? ", \"{$this->lexeme}\"" : '';

        return "Token({$label}{$lexeme}, {$this->span})";
    }
}
