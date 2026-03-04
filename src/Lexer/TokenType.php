<?php

declare(strict_types=1);

namespace PslToml\Lexer;

use Psl\Vec;

/**
 * Canonical set of token kinds produced by the TOML lexer.
 *
 * Every token that can appear in a well-formed — or malformed — TOML document
 * is represented here.  The enum intentionally carries no backing type so that
 * it cannot be confused with an integer or string value; use {@see self::symbol()}
 * and {@see self::label()} when you need a human-readable representation.
 *
 * @see https://toml.io/en/v1.1.0
 *
 * @mago-ignore linter:too-many-enum-cases,kan-defect,too-many-methods
 */
enum TokenType
{
    // -------------------------------------------------------------------------
    // Structural / punctuation
    // -------------------------------------------------------------------------

    /** {@code =} — separates a key from its value. */
    case Equals;

    /** {@code .} — separates segments in a dotted key. */
    case Dot;

    /** {@code ,} — separates elements in an array or inline table. */
    case Comma;

    /** {@code :} — separates hours, minutes, and seconds in time literals. */
    case Colon;

    /** {@code [} — opens a table header or an array value. */
    case LeftBracket;

    /** {@code ]} — closes a table header or an array value. */
    case RightBracket;

    /** {@code [[} — opens an array-of-tables header. */
    case DoubleLeftBracket;

    /** {@code ]]} — closes an array-of-tables header. */
    case DoubleRightBracket;

    /** {@code \{} — opens an inline table. */
    case LeftBrace;

    /** {@code \}} — closes an inline table. */
    case RightBrace;

    // -------------------------------------------------------------------------
    // Keys
    // -------------------------------------------------------------------------

    /**
     * A bare (unquoted) key composed of {@code [A-Za-z0-9_-]+}.
     *
     * Quoted keys are represented by {@see self::BasicString} and
     * {@see self::LiteralString} — the parser is responsible for
     * distinguishing key context from value context.
     */
    case BareKey;

    // -------------------------------------------------------------------------
    // String literals
    // -------------------------------------------------------------------------

    /**
     * A basic string delimited by {@code "…"}.
     *
     * Escape sequences (e.g. {@code \n}, {@code \uXXXX}) are interpreted
     * by the lexer and stored in their resolved form.
     */
    case BasicString;

    /**
     * A multi-line basic string delimited by {@code """…"""}.
     *
     * A newline immediately following the opening delimiter is trimmed.
     * Escape sequences are interpreted.
     */
    case MultiLineBasicString;

    /**
     * A literal string delimited by {@code '…'}.
     *
     * No escape processing is performed; the value is taken verbatim.
     */
    case LiteralString;

    /**
     * A multi-line literal string delimited by {@code '''…'''}.
     *
     * A newline immediately following the opening delimiter is trimmed.
     * No escape processing is performed.
     */
    case MultiLineLiteralString;

    // -------------------------------------------------------------------------
    // Integer literals
    // -------------------------------------------------------------------------

    /**
     * A decimal integer, e.g. {@code 42}, {@code -17}, {@code 1_000_000}.
     *
     * Leading zeros are forbidden (except for {@code 0} itself).
     * Underscores may appear between digits as visual separators.
     */
    case Integer;

    /**
     * A hexadecimal integer prefixed with {@code 0x}, e.g. {@code 0xDEAD_BEEF}.
     *
     * Digits {@code A–F} are accepted in both upper and lower case.
     */
    case HexInteger;

    /**
     * An octal integer prefixed with {@code 0o}, e.g. {@code 0o755}.
     */
    case OctalInteger;

    /**
     * A binary integer prefixed with {@code 0b}, e.g. {@code 0b1101_0110}.
     */
    case BinaryInteger;

    // -------------------------------------------------------------------------
    // Float literals
    // -------------------------------------------------------------------------

    /**
     * A floating-point number, e.g. {@code 3.14}, {@code -0.01}, {@code 6.626e-34}.
     *
     * Both a decimal part and an exponent part are optional, but at least
     * one of them must be present alongside an integer part.
     */
    case Float;

    /**
     * The special float value {@code inf}, {@code +inf}, or {@code -inf}.
     */
    case Inf;

    /**
     * The special float value {@code nan}, {@code +nan}, or {@code -nan}.
     */
    case Nan;

    // -------------------------------------------------------------------------
    // Boolean literals
    // -------------------------------------------------------------------------

    /** The boolean literal {@code true}. */
    case True;

    /** The boolean literal {@code false}. */
    case False;

    // -------------------------------------------------------------------------
    // Date / time literals  (RFC 3339 subsets)
    // -------------------------------------------------------------------------

    /**
     * An offset date-time, e.g. {@code 1979-05-27T07:32:00Z} or
     * {@code 1979-05-27 07:32:00+02:00}.
     *
     * The {@code T} separator may be replaced by a single space character.
     */
    case OffsetDateTime;

    /**
     * A local date-time without timezone information,
     * e.g. {@code 1979-05-27T07:32:00}.
     */
    case LocalDateTime;

    /**
     * A local date without time or timezone, e.g. {@code 1979-05-27}.
     */
    case LocalDate;

    /**
     * A local time without date or timezone, e.g. {@code 07:32:00.999999}.
     */
    case LocalTime;

    // -------------------------------------------------------------------------
    // Trivia  (whitespace, newlines, comments)
    // -------------------------------------------------------------------------

    /**
     * One or more {@code U+0020} SPACE or {@code U+0009} TAB characters.
     *
     * Trivia tokens are typically discarded by the parser but are retained
     * by the lexer to allow lossless source reconstruction.
     */
    case Whitespace;

    /**
     * A line ending: {@code \n} (LF) or {@code \r\n} (CRLF).
     *
     * Bare {@code \r} is illegal in TOML and will produce {@see self::Invalid}.
     */
    case Newline;

    /**
     * A comment starting with {@code #} and running to the end of the line.
     *
     * The trailing newline is **not** included; it is emitted as a separate
     * {@see self::Newline} token.
     */
    case Comment;

    // -------------------------------------------------------------------------
    // Control / sentinel
    // -------------------------------------------------------------------------

    /**
     * Signals that the lexer has consumed the entire input stream.
     *
     * The parser treats this as the implicit terminator of every production.
     * Exactly one {@see self::Eof} token is emitted per document, always last.
     */
    case Eof;

    /**
     * An unrecognised or structurally illegal character.
     *
     * The lexer never throws on encountering bad input; it emits an
     * {@see self::Invalid} token instead and lets the parser decide whether
     * to recover or to surface a {@see \PslToml\Exception\ParseException}.
     */
    case Invalid;

    // =========================================================================
    // Utility methods
    // =========================================================================

    /**
     * Returns the canonical source symbol for punctuation tokens, or {@code null}
     * for token kinds whose lexeme varies per occurrence (strings, keys, etc.).
     *
     * Useful for building diagnostic messages and for table-driven parsing.
     *
     * @return non-empty-string|null
     */
    public function symbol(): ?string
    {
        return match ($this) {
            self::Equals => '=',
            self::Dot => '.',
            self::Comma => ',',
            self::Colon => ':',
            self::LeftBracket => '[',
            self::RightBracket => ']',
            self::DoubleLeftBracket => '[[',
            self::DoubleRightBracket => ']]',
            self::LeftBrace => '{',
            self::RightBrace => '}',
            self::True => 'true',
            self::False => 'false',
            self::Inf => 'inf',
            self::Nan => 'nan',
            self::Eof => '<eof>',
            default => null,
        };
    }

    /**
     * Returns a short, human-readable label suitable for use inside parser
     * error messages (e.g. {@code "expected '=', got <integer>"}).
     *
     * @return non-empty-string
     */
    public function label(): string
    {
        return match ($this) {
            self::Equals => "'='",
            self::Dot => "'.'",
            self::Comma => "','",
            self::Colon => "':'",
            self::LeftBracket => "'['",
            self::RightBracket => "']'",
            self::DoubleLeftBracket => "'[['",
            self::DoubleRightBracket => "']]'",
            self::LeftBrace => "'{'",
            self::RightBrace => "'}'",
            self::BareKey => '<bare-key>',
            self::BasicString => '<basic-string>',
            self::MultiLineBasicString => '<multi-line-basic-string>',
            self::LiteralString => '<literal-string>',
            self::MultiLineLiteralString => '<multi-line-literal-string>',
            self::Integer => '<integer>',
            self::HexInteger => '<hex-integer>',
            self::OctalInteger => '<octal-integer>',
            self::BinaryInteger => '<binary-integer>',
            self::Float => '<float>',
            self::Inf => '<inf>',
            self::Nan => '<nan>',
            self::True => 'true',
            self::False => 'false',
            self::OffsetDateTime => '<offset-date-time>',
            self::LocalDateTime => '<local-date-time>',
            self::LocalDate => '<local-date>',
            self::LocalTime => '<local-time>',
            self::Whitespace => '<whitespace>',
            self::Newline => '<newline>',
            self::Comment => '<comment>',
            self::Eof => '<eof>',
            self::Invalid => '<invalid>',
        };
    }

    /**
     * Returns {@code true} for token kinds that carry a variable lexeme
     * (strings, numbers, keys, date/time values).
     *
     * Punctuation, booleans, and control tokens are **not** considered literals.
     */
    public function isLiteral(): bool
    {
        return match ($this) {
            self::BareKey,
            self::BasicString,
            self::MultiLineBasicString,
            self::LiteralString,
            self::MultiLineLiteralString,
            self::Integer,
            self::HexInteger,
            self::OctalInteger,
            self::BinaryInteger,
            self::Float,
            self::Inf,
            self::Nan,
            self::OffsetDateTime,
            self::LocalDateTime,
            self::LocalDate,
            self::LocalTime,
                => true,
            default => false,
        };
    }

    /**
     * Returns {@code true} for token kinds that represent any string variant.
     *
     * Covers both basic and literal strings in their single- and multi-line forms.
     */
    public function isString(): bool
    {
        return match ($this) {
            self::BasicString, self::MultiLineBasicString, self::LiteralString, self::MultiLineLiteralString => true,
            default => false,
        };
    }

    /**
     * Returns {@code true} for token kinds that represent any integer variant.
     */
    public function isInteger(): bool
    {
        return match ($this) {
            self::Integer, self::HexInteger, self::OctalInteger, self::BinaryInteger => true,
            default => false,
        };
    }

    /**
     * Returns {@code true} for token kinds that represent any numeric value,
     * including floats and their special variants.
     */
    public function isNumeric(): bool
    {
        return $this->isInteger()
        || match ($this) {
            self::Float, self::Inf, self::Nan => true,
            default => false,
        };
    }

    /**
     * Returns {@code true} for token kinds that represent any date or time value.
     */
    public function isTemporal(): bool
    {
        return match ($this) {
            self::OffsetDateTime, self::LocalDateTime, self::LocalDate, self::LocalTime => true,
            default => false,
        };
    }

    /**
     * Returns {@code true} for trivia tokens: {@see self::Whitespace},
     * {@see self::Newline}, and {@see self::Comment}.
     *
     * The parser skips trivia in most contexts, but the lexer always emits
     * them to support lossless round-tripping and formatting tools.
     */
    public function isTrivia(): bool
    {
        return match ($this) {
            self::Whitespace, self::Newline, self::Comment => true,
            default => false,
        };
    }

    /**
     * Returns {@code true} if this token kind can legally appear as a key
     * in a key/value pair or table header.
     *
     * Per the TOML spec, bare keys, basic strings, and literal strings are
     * all valid key forms.
     */
    public function isKey(): bool
    {
        return match ($this) {
            self::BareKey, self::BasicString, self::LiteralString => true,
            default => false,
        };
    }

    /**
     * Returns {@code true} if this token kind can appear as a scalar value
     * on the right-hand side of a key/value pair.
     */
    public function isValue(): bool
    {
        return $this->isString()
        || $this->isNumeric()
        || $this->isTemporal()
        || match ($this) {
            self::True, self::False => true,
            default => false,
        };
    }

    /**
     * Returns all token kinds for which {@code $predicate} returns {@code true}.
     *
     * The result is always a re-indexed {@code list<self>} with no key gaps.
     *
     * @param  \Closure(self): bool  $predicate
     * @return list<self>
     *
     * @example
     *   // All token kinds that carry a variable lexeme:
     *   $literals = TokenType::filter(static fn(TokenType $t): bool => $t->isLiteral());
     *
     *   // All trivia kinds:
     *   $trivia = TokenType::filter(static fn(TokenType $t): bool => $t->isTrivia());
     */
    public static function filter(\Closure $predicate): array
    {
        return Vec\filter(self::cases(), $predicate);
    }
}
