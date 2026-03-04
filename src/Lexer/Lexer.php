<?php

declare(strict_types=1);

namespace PslToml\Lexer;

use Psl\Regex;
use Psl\Str\Byte;

/**
 * Transforms a TOML source document into a lazy stream of {@see Token} values.
 *
 * The lexer is intentionally fault-tolerant: when it encounters an unrecognised
 * character it emits a {@see TokenType::Invalid} token instead of throwing.
 * It is the parser's responsibility to decide whether to recover or to surface
 * a {@see \PslToml\Exception\ParseException}.
 *
 * Trivia tokens ({@see TokenType::Whitespace}, {@see TokenType::Newline},
 * {@see TokenType::Comment}) are always emitted and must be skipped by the
 * parser when they are not significant.
 *
 * @see Token
 * @see TokenType
 *
 * @mago-ignore linter:too-many-methods,kan-defect,cyclomatic-complexity
 */
final class Lexer
{
    /** @var int<0, max> */
    private int $cursor = 0;

    /** @var int<0, max> */
    private readonly int $length;

    /**
     * @param non-empty-string $source
     */
    public function __construct(
        private readonly string $source,
    ) {
        $this->length = Byte\length($source);
    }

    /**
     * Produces a lazy stream of tokens from the source document.
     *
     * The generator yields one {@see Token} per iteration and always ends
     * with a {@see TokenType::Eof} sentinel. Callers may stop consuming
     * at any point without penalty.
     *
     * @return \Generator<int, Token, never, void>
     *
     * @throws \Psl\Type\Exception\AssertException
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Regex\Exception\InvalidPatternException
     * @throws \Psl\Regex\Exception\RuntimeException
     */
    public function tokenize(): \Generator
    {
        while (!$this->isAtEnd()) {
            yield $this->nextToken();
        }

        yield Token::eof($this->cursor);
    }

    /**
     * @throws \Psl\Type\Exception\AssertException
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Regex\Exception\InvalidPatternException
     * @throws \Psl\Regex\Exception\RuntimeException
     */
    private function nextToken(): Token
    {
        $start = $this->cursor;
        /** @var non-empty-string $char */
        $char = $this->advance();

        return match (true) {
            $char === '=' => $this->makeToken(TokenType::Equals, $start),
            $char === '.' => $this->makeToken(TokenType::Dot, $start),
            $char === ',' => $this->makeToken(TokenType::Comma, $start),
            $char === ':' => $this->makeToken(TokenType::Colon, $start),
            $char === '{' => $this->makeToken(TokenType::LeftBrace, $start),
            $char === '}' => $this->makeToken(TokenType::RightBrace, $start),
            $char === '[' && $this->peek() === '[' => $this->consumeDoubleLeftBracket($start),
            $char === '[' && $this->peek() !== '[' => $this->makeToken(TokenType::LeftBracket, $start),
            $char === ']' && $this->peek() === ']' => $this->consumeDoubleRightBracket($start),
            $char === ']' && $this->peek() !== ']' => $this->makeToken(TokenType::RightBracket, $start),
            $char === '#' => $this->consumeComment($start),
            $char === "\n" => $this->makeToken(TokenType::Newline, $start),
            $char === "\r" => $this->consumeCrLf($start),
            $char === ' ' || $char === "\t" => $this->consumeWhitespace($start),
            $char === '"' => $this->consumeBasicString($start),
            $char === "'" => $this->consumeLiteralString($start),
            $this->isDigit($char) => $this->consumeNumberOrDateTime($start, $char),
            $char === 't' => $this->consumeKeyword($start, 'true', TokenType::True),
            $char === 'f' => $this->consumeKeyword($start, 'false', TokenType::False),
            $char === 'i' => $this->consumeKeyword($start, 'inf', TokenType::Inf),
            $char === 'n' => $this->consumeKeyword($start, 'nan', TokenType::Nan),
            $char === '+' || $char === '-' => $this->consumeSignedNumber($start, $char),
            $this->isBareKeyChar($char) => $this->consumeBareKey($start),
            default => Token::invalid($char, $this->spanFrom($start)),
        };
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeDoubleLeftBracket(int $start): Token
    {
        $this->advance();

        return $this->makeToken(TokenType::DoubleLeftBracket, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeDoubleRightBracket(int $start): Token
    {
        $this->advance();

        return $this->makeToken(TokenType::DoubleRightBracket, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeCrLf(int $start): Token
    {
        if ($this->peek() === "\n") {
            $this->advance();

            return $this->makeToken(TokenType::Newline, $start);
        }

        return Token::invalid("\r", $this->spanFrom($start));
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeWhitespace(int $start): Token
    {
        while (!$this->isAtEnd() && ($this->peek() === ' ' || $this->peek() === "\t")) {
            $this->advance();
        }

        return $this->makeToken(TokenType::Whitespace, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeComment(int $start): Token
    {
        while (!$this->isAtEnd() && $this->peek() !== "\n" && $this->peek() !== "\r") {
            $this->advance();
        }

        return $this->makeToken(TokenType::Comment, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeBasicString(int $start): Token
    {
        if ($this->peek() === '"' && $this->peekAt(1) === '"') {
            $this->advance();
            $this->advance();

            return $this->consumeMultiLineBasicString($start);
        }

        while (!$this->isAtEnd() && $this->peek() !== '"') {
            if ($this->peek() === '\\') {
                $this->advance();
            }
            $this->advance();
        }

        $this->advance();

        return $this->makeToken(TokenType::BasicString, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeMultiLineBasicString(int $start): Token
    {
        while (!$this->isAtEnd()) {
            if ($this->peek() === '"' && $this->peekAt(1) === '"' && $this->peekAt(2) === '"') {
                $this->advance();
                $this->advance();
                $this->advance();
                break;
            }

            if ($this->peek() === '\\') {
                $this->advance();
            }

            $this->advance();
        }

        return $this->makeToken(TokenType::MultiLineBasicString, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeLiteralString(int $start): Token
    {
        if ($this->peek() === "'" && $this->peekAt(1) === "'") {
            $this->advance();
            $this->advance();

            return $this->consumeMultiLineLiteralString($start);
        }

        while (!$this->isAtEnd() && $this->peek() !== "'") {
            $this->advance();
        }

        $this->advance();

        return $this->makeToken(TokenType::LiteralString, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeMultiLineLiteralString(int $start): Token
    {
        while (!$this->isAtEnd()) {
            if ($this->peek() === "'" && $this->peekAt(1) === "'" && $this->peekAt(2) === "'") {
                $this->advance();
                $this->advance();
                $this->advance();
                break;
            }

            $this->advance();
        }

        return $this->makeToken(TokenType::MultiLineLiteralString, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Regex\Exception\InvalidPatternException
     * @throws \Psl\Regex\Exception\RuntimeException
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeNumberOrDateTime(int $start, string $firstChar): Token
    {
        if ($firstChar === '0') {
            $next = $this->peek();

            if ($next === 'x') {
                $this->advance();

                return $this->consumeIntegerWithPattern($start, TokenType::HexInteger, '/^[0-9a-fA-F_]+$/');
            }

            if ($next === 'o') {
                $this->advance();

                return $this->consumeIntegerWithPattern($start, TokenType::OctalInteger, '/^[0-7_]+$/');
            }

            if ($next === 'b') {
                $this->advance();

                return $this->consumeIntegerWithPattern($start, TokenType::BinaryInteger, '/^[01_]+$/');
            }
        }

        while (!$this->isAtEnd() && $this->isNumberOrDateTimeChar($this->peek())) {
            $this->advance();
        }

        $lexeme = $this->sliceFrom($start);

        return match (true) {
            Regex\matches($lexeme, '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/')
                => $this->makeToken(TokenType::OffsetDateTime, $start),
            Regex\matches($lexeme, '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(\.\d+)?$/') => $this->makeToken(
                TokenType::LocalDateTime,
                $start,
            ),
            Regex\matches($lexeme, '/^\d{4}-\d{2}-\d{2}$/') => $this->makeToken(TokenType::LocalDate, $start),
            Regex\matches($lexeme, '/^\d{2}:\d{2}:\d{2}(\.\d+)?$/') => $this->makeToken(TokenType::LocalTime, $start),
            Regex\matches($lexeme, '/^[0-9][0-9_]*\.[0-9_]*([eE][+-]?[0-9_]+)?$/') => $this->makeToken(
                TokenType::Float,
                $start,
            ),
            Regex\matches($lexeme, '/^[0-9][0-9_]*([eE][+-]?[0-9_]+)$/') => $this->makeToken(TokenType::Float, $start),
            default => $this->makeToken(TokenType::Integer, $start),
        };
    }

    /**
     * @param int<0, max>      $start
     * @param non-empty-string $pattern
     *
     * @throws \Psl\Regex\Exception\InvalidPatternException
     * @throws \Psl\Regex\Exception\RuntimeException
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeIntegerWithPattern(int $start, TokenType $type, string $pattern): Token
    {
        while (!$this->isAtEnd() && Regex\matches((string) $this->peek(), $pattern)) {
            $this->advance();
        }

        return $this->makeToken($type, $start);
    }

    /**
     * @param int<0, max>      $start
     * @param non-empty-string $sign
     *
     * @throws \Psl\Regex\Exception\InvalidPatternException
     * @throws \Psl\Regex\Exception\RuntimeException
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeSignedNumber(int $start, string $sign): Token
    {
        $next = $this->peek();

        if ($next === 'i') {
            return $this->consumeKeyword($start, $sign . 'inf', TokenType::Inf);
        }

        if ($next === 'n') {
            return $this->consumeKeyword($start, $sign . 'nan', TokenType::Nan);
        }

        if ($next !== null && $this->isDigit($next)) {
            $this->advance();

            return $this->consumeNumberOrDateTime($start, $next);
        }

        return Token::invalid($sign, $this->spanFrom($start));
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeKeyword(int $start, string $keyword, TokenType $type): Token
    {
        $remaining = Byte\slice($keyword, 1);

        foreach (Byte\chunk($remaining, 1) as $char) {
            if ($this->peek() === $char) {
                $this->advance();
                continue;
            }

            return $this->consumeBareKey($start);
        }

        if (!$this->isAtEnd() && $this->isBareKeyChar($this->peek())) {
            return $this->consumeBareKey($start);
        }

        return $this->makeToken($type, $start);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function consumeBareKey(int $start): Token
    {
        while (!$this->isAtEnd() && $this->isBareKeyChar($this->peek())) {
            $this->advance();
        }

        return $this->makeToken(TokenType::BareKey, $start);
    }

    /**
     * @throws \Psl\Str\Exception\OutOfBoundsException
     */
    private function advance(): string
    {
        $char = Byte\slice($this->source, $this->cursor, 1);
        $this->cursor++;

        return $char;
    }

    /**
     * @throws \Psl\Str\Exception\OutOfBoundsException
     */
    private function peek(): ?string
    {
        if ($this->isAtEnd()) {
            return null;
        }

        return Byte\slice($this->source, $this->cursor, 1);
    }

    /**
     * @throws \Psl\Str\Exception\OutOfBoundsException
     */
    private function peekAt(int $offset): ?string
    {
        $position = $this->cursor + $offset;

        if ($position >= $this->length) {
            return null;
        }

        return Byte\slice($this->source, $position, 1);
    }

    private function isAtEnd(): bool
    {
        return $this->cursor >= $this->length;
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Type\Exception\AssertException
     */
    private function spanFrom(int $start): Span
    {
        return new Span($start, $this->cursor);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     * @throws \Psl\Type\Exception\AssertException
     */
    private function makeToken(TokenType $type, int $start): Token
    {
        $span = $this->spanFrom($start);

        return new Token($type, $this->sliceFrom($start), $span);
    }

    /**
     * @param int<0, max> $start
     *
     * @throws \Psl\Str\Exception\OutOfBoundsException
     */
    private function sliceFrom(int $start): string
    {
        /** @var int<0, max> $length */
        $length = $this->cursor - $start;

        return Byte\slice($this->source, $start, $length);
    }

    private function isDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private function isBareKeyChar(?string $char): bool
    {
        if ($char === null) { // @codeCoverageIgnore
            return false; // @codeCoverageIgnore
        }

        return (
            $char >= 'a'
            && $char <= 'z'
            || $char >= 'A'
            && $char <= 'Z'
            || $char >= '0'
            && $char <= '9'
            || $char === '_'
            || $char === '-'
        );
    }

    private function isNumberOrDateTimeChar(?string $char): bool
    {
        if ($char === null) { // @codeCoverageIgnore
            return false; // @codeCoverageIgnore
        }

        return (
            $this->isDigit($char)
            || $char === '.'
            || $char === '_'
            || $char === '-'
            || $char === '+'
            || $char === ':'
            || $char === 'T'
            || $char === 'Z'
            || $char === 'e'
            || $char === 'E'
        );
    }
}
