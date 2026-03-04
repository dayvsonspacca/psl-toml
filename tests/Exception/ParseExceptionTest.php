<?php

declare(strict_types=1);

namespace PslToml\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PslToml\Exception\ParseException;
use PslToml\Lexer\Span;

final class ParseExceptionTest extends TestCase
{
    #[Test]
    public function message_contains_reason_line_and_column(): void
    {
        $exception = new ParseException('name = "toml"', new Span(7, 13), 'unexpected token');

        $this->assertSame('unexpected token at line 1, column 8', $exception->getMessage());
    }

    #[Test]
    public function message_contains_correct_line_when_error_is_on_second_line(): void
    {
        $source    = "name = \"toml\"\nversion = 1!";
        $exception = new ParseException($source, new Span(25, 26), 'unexpected character');

        $this->assertSame('unexpected character at line 2, column 12', $exception->getMessage());
    }

    #[Test]
    public function previous_exception_is_chained(): void
    {
        $previous  = new \RuntimeException('original error');
        $exception = new ParseException('name = 1', new Span(7, 8), 'unexpected token', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function lexeme_returns_correct_slice_of_source(): void
    {
        $exception = new ParseException('name = "toml"', new Span(7, 13), 'unexpected token');

        $this->assertSame('"toml"', $exception->lexeme());
    }

    #[Test]
    public function lexeme_returns_single_character_for_single_byte_span(): void
    {
        $exception = new ParseException('name = $', new Span(7, 8), 'unexpected character');

        $this->assertSame('$', $exception->lexeme());
    }
}
