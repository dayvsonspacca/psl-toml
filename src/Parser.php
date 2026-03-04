<?php

declare(strict_types=1);

namespace PslToml;

use Psl\DataStructure;
use Psl\Iter;
use Psl\Math;
use Psl\Result\Failure;
use Psl\Result\ResultInterface;
use Psl\Result\Success;
use Psl\Str;
use Psl\Str\Byte;
use Psl\Vec;
use PslToml\Exception\ParseException;
use PslToml\Lexer\Lexer;
use PslToml\Lexer\Token;
use PslToml\Lexer\TokenType;

/**
 * Transforms a TOML source document into an immutable {@see Document}.
 *
 * Consumes the token stream produced by {@see Lexer}, skips all trivia
 * (whitespace, newlines, comments), and builds a nested PHP array that is
 * wrapped in a {@see Document}.
 *
 * The public entry point {@see self::parse()} wraps any {@see ParseException}
 * in a {@see Result\Failure} so callers never need a try/catch.
 *
 * @see Lexer
 * @see Document
 *
 * @mago-ignore linter:too-many-methods,cyclomatic-complexity,kan-defect,sensitive-parameter,halstead
 */
final class Parser
{
    /** @var DataStructure\Queue<Token> */
    private DataStructure\Queue $tokens;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var list<string> */
    private array $currentPath = [];

    private bool $currentIsArrayTable = false;

    /**
     * @param non-empty-string $source
     */
    public function __construct(
        private readonly string $source,
    ) {}

    /**
     * Parses {@code $source} and returns a {@see Result\Success} containing
     * the {@see Document}, or a {@see Result\Failure} containing the
     * {@see ParseException} that caused the error.
     *
     * @return ResultInterface<Document>
     */
    public function parse(): ResultInterface
    {
        try {
            $this->collectTokens();
            $this->parseDocument();

            return new Success(new Document($this->data));
        } catch (ParseException $e) {
            return new Failure($e);
        }
    }

    private function collectTokens(): void
    {
        $this->tokens = new DataStructure\Queue();
        $lexer = new Lexer($this->source);

        foreach ($lexer->tokenize() as $token) {
            if ($token->isTrivia()) {
                continue;
            }
            $this->tokens->enqueue($token);
        }
    }

    private function parseDocument(): void
    {
        while (!$this->isAtEnd()) {
            $token = $this->peek();

            match (true) {
                $token->is(TokenType::DoubleLeftBracket) => $this->parseArrayOfTablesHeader(),
                $token->is(TokenType::LeftBracket) => $this->parseTableHeader(),
                $token->isKey() => $this->parseKeyValue(),
                default => throw $this->error($token, "unexpected token {$token->type->label()}"),
            };
        }
    }

    private function parseTableHeader(): void
    {
        $this->consume(TokenType::LeftBracket);
        $key = $this->parseKey();
        $this->consume(TokenType::RightBracket);

        $this->currentPath = $key;
        $this->currentIsArrayTable = false;

        $ref = &$this->data;

        foreach ($key as $segment) {
            if (!is_array($ref[$segment] ?? null)) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }
    }

    private function parseArrayOfTablesHeader(): void
    {
        $this->consume(TokenType::DoubleLeftBracket);
        $key = $this->parseKey();
        $this->consume(TokenType::DoubleRightBracket);

        $this->currentPath = $key;
        $this->currentIsArrayTable = true;

        $segments = $key;
        $last = array_pop($segments);

        if ($last === null) { // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }

        $ref = &$this->data;

        foreach ($segments as $segment) {
            if (!is_array($ref[$segment] ?? null)) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        if (!is_array($ref[$last] ?? null)) {
            $ref[$last] = [];
        }

        $ref[$last][] = [];
    }

    private function parseKeyValue(): void
    {
        $keySegments = $this->parseKey();
        $this->consume(TokenType::Equals);
        $value = $this->parseValue();

        if ($this->currentIsArrayTable && $this->currentPath !== []) {
            $ref = &$this->data;
            $pathSegments = $this->currentPath;
            $arraySegment = array_pop($pathSegments);

            if ($arraySegment === null) { // @codeCoverageIgnore
                return; // @codeCoverageIgnore
            }

            foreach ($pathSegments as $segment) {
                $ref = &$ref[$segment];
            }

            $idx = Iter\count($ref[$arraySegment]) - 1;
            $ref = &$ref[$arraySegment][$idx];

            $this->setNestedValue($ref, $keySegments, $value);
            return;
        }

        $fullPath = Vec\concat($this->currentPath, $keySegments);
        $this->setNestedValue($this->data, $fullPath, $value);
    }

    /**
     * @return list<string>
     */
    private function parseKey(): array
    {
        $segments = [$this->parseSimpleKey()];

        while (!$this->isAtEnd() && $this->peek()->is(TokenType::Dot)) {
            $this->advance();
            $segments[] = $this->parseSimpleKey();
        }

        return $segments;
    }

    private function parseSimpleKey(): string
    {
        $token = $this->peek();

        if (!$token->isKey()) {
            throw $this->error($token, "expected key, got {$token->type->label()}");
        }

        $this->advance();

        return match ($token->type) {
            TokenType::BareKey => $token->lexeme,
            TokenType::BasicString => $this->decodeBasicString($token),
            TokenType::LiteralString => $this->decodeLiteralString($token),
            default => throw $this->error($token, "unexpected key type {$token->type->label()}"), // @codeCoverageIgnore
        };
    }

    private function parseValue(): mixed
    {
        $token = $this->peek();

        if ($token->is(TokenType::LeftBracket)) {
            return $this->parseArray();
        }

        if ($token->is(TokenType::LeftBrace)) {
            return $this->parseInlineTable();
        }

        if ($token->is(TokenType::Invalid)) {
            throw $this->error($token, "invalid character '{$token->lexeme}'");
        }

        if ($token->isEof()) {
            throw $this->error($token, 'unexpected end of file, expected value');
        }

        $this->advance();

        return match (true) {
            $token->type->isString() => $this->parseString($token),
            $token->type->isInteger() => $this->parseInteger($token),
            $token->is(TokenType::Float) => $this->parseFloat($token),
            $token->is(TokenType::Inf) => $this->parseInf($token),
            $token->is(TokenType::Nan) => NAN,
            $token->is(TokenType::True) => true,
            $token->is(TokenType::False) => false,
            $token->type->isTemporal() => $this->parseDateTime($token),
            default => throw $this->error($token, "unexpected token {$token->type->label()} in value position"),
        };
    }

    private function parseString(Token $token): string
    {
        return match ($token->type) {
            TokenType::BasicString => $this->decodeBasicString($token),
            TokenType::MultiLineBasicString => $this->decodeMultiLineBasicString($token),
            TokenType::LiteralString => $this->decodeLiteralString($token),
            TokenType::MultiLineLiteralString => $this->decodeMultiLineLiteralString($token),
            default => throw $this->error($token, 'expected string'), // @codeCoverageIgnore
        };
    }

    private function parseInteger(Token $token): int
    {
        $lexeme = Str\replace($token->lexeme, '_', '');

        return match ($token->type) {
            TokenType::Integer => (int) $lexeme,
            TokenType::HexInteger => (int) Math\base_convert(Byte\slice($lexeme, 2), 16, 10),
            TokenType::OctalInteger => (int) octdec(Byte\slice($lexeme, 2)),
            TokenType::BinaryInteger => (int) Math\from_base(Byte\slice($lexeme, 2), 2),
            default => throw $this->error($token, 'expected integer'), // @codeCoverageIgnore
        };
    }

    private function parseFloat(Token $token): float
    {
        return (float) Str\replace($token->lexeme, '_', '');
    }

    private function parseInf(Token $token): float
    {
        return Str\starts_with($token->lexeme, '-') ? -INF : INF;
    }

    private function parseDateTime(Token $token): \DateTimeImmutable
    {
        $lexeme = $token->lexeme;

        $dt = match ($token->type) {
            TokenType::OffsetDateTime => $this->tryFormats(
                $lexeme,
                \DateTimeInterface::RFC3339_EXTENDED,
                \DateTimeInterface::RFC3339,
                'Y-m-d H:i:sP',
                'Y-m-d H:i:s.uP',
            ),
            TokenType::LocalDateTime => $this->tryFormats(
                $lexeme,
                'Y-m-d\TH:i:s.u',
                'Y-m-d\TH:i:s',
                'Y-m-d H:i:s.u',
                'Y-m-d H:i:s',
            ),
            TokenType::LocalDate => $this->tryFormats($lexeme, 'Y-m-d'),
            TokenType::LocalTime => $this->tryFormats($lexeme, 'H:i:s.u', 'H:i:s'),
            default => null, // @codeCoverageIgnore
        };

        if ($dt === null) { // @codeCoverageIgnore
            throw $this->error($token, "invalid date/time value '{$lexeme}'"); // @codeCoverageIgnore
        } // @codeCoverageIgnore

        return $dt;
    }

    /**
     * @return list<mixed>
     */
    private function parseArray(): array
    {
        $this->consume(TokenType::LeftBracket);
        $values = [];

        while (!$this->isAtEnd() && !$this->peek()->is(TokenType::RightBracket)) {
            $values[] = $this->parseValue();

            if (!$this->peek()->is(TokenType::Comma)) {
                break;
            }

            $this->advance();
        }

        $this->consume(TokenType::RightBracket);

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseInlineTable(): array
    {
        $this->consume(TokenType::LeftBrace);
        $result = [];

        while (!$this->isAtEnd() && !$this->peek()->is(TokenType::RightBrace)) {
            $keySegments = $this->parseKey();
            $this->consume(TokenType::Equals);
            $value = $this->parseValue();
            $this->setNestedValue($result, $keySegments, $value);

            if (!$this->peek()->is(TokenType::Comma)) {
                break;
            }

            $this->advance();
        }

        $this->consume(TokenType::RightBrace);

        return $result;
    }

    private function decodeBasicString(Token $token): string
    {
        return $this->decodeEscapes(Byte\slice($token->lexeme, 1, -1));
    }

    private function decodeMultiLineBasicString(Token $token): string
    {
        $inner = Byte\slice($token->lexeme, 3, -3);

        $inner = match (true) {
            Str\starts_with($inner, "\r\n") => Byte\slice($inner, 2),
            Str\starts_with($inner, "\n") => Byte\slice($inner, 1),
            default => $inner,
        };

        return $this->decodeEscapes($inner);
    }

    private function decodeLiteralString(Token $token): string
    {
        return Byte\slice($token->lexeme, 1, -1);
    }

    private function decodeMultiLineLiteralString(Token $token): string
    {
        $inner = Byte\slice($token->lexeme, 3, -3);

        return match (true) {
            Str\starts_with($inner, "\r\n") => Byte\slice($inner, 2),
            Str\starts_with($inner, "\n") => Byte\slice($inner, 1),
            default => $inner,
        };
    }

    private function decodeEscapes(string $s): string
    {
        $result = '';
        $i = 0;
        $len = Byte\length($s);

        while ($i < $len) {
            if ($s[$i] !== '\\') {
                $result .= $s[$i++];
                continue;
            }

            $i++;

            if ($i >= $len) {
                break;
            }

            $c = $s[$i++];

            $result .= match ($c) {
                'b' => "\x08",
                't' => "\t",
                'n' => "\n",
                'f' => "\x0C",
                'r' => "\r",
                'e' => "\x1B",
                '"' => '"',
                '\\' => '\\',
                'u' => $this->decodeUnicodeEscape($s, $i, 4),
                'U' => $this->decodeUnicodeEscape($s, $i, 8),
                default => '\\' . $c,
            };
        }

        return $result;
    }

    private function decodeUnicodeEscape(string $s, int &$i, int $length): string
    {
        $hex = Byte\slice($s, $i, $length);
        $i += $length;
        $codepoint = (int) Math\from_base($hex, 16);

        return Str\chr($codepoint, Str\Encoding::Utf8) ?? '';
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $path
     * @param mixed                $value
     */
    private function setNestedValue(array &$data, array $path, mixed $value): void
    {
        $last = array_pop($path);

        if ($last === null) { // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }

        $ref = &$data;

        foreach ($path as $segment) {
            if (!is_array($ref[$segment] ?? null)) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref[$last] = $value;
    }

    /**
     * @mago-ignore linter:psl-datetime
     */
    private function tryFormats(string $value, string ...$formats): ?\DateTimeImmutable
    {
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);

            if ($dt !== false) {
                return $dt;
            }
        }

        return null; // @codeCoverageIgnore
    }

    private function peek(): Token
    {
        return $this->tokens->peek() ?? Token::eof(0); // @codeCoverageIgnore
    }

    private function advance(): Token
    {
        return $this->tokens->dequeue();
    }

    private function consume(TokenType $type): Token
    {
        $token = $this->peek();

        if (!$token->is($type)) {
            throw $this->error($token, "expected {$type->label()}, got {$token->type->label()}");
        }

        return $this->advance();
    }

    private function isAtEnd(): bool
    {
        return $this->tokens->peek()?->isEof() ?? true;
    }

    private function error(Token $token, string $reason): ParseException
    {
        return new ParseException($this->source, $token->span, $reason);
    }
}
