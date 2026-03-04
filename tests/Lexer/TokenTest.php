<?php

declare(strict_types=1);

namespace PslToml\Tests\Lexer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PslToml\Lexer\Span;
use PslToml\Lexer\Token;
use PslToml\Lexer\TokenType;

final class TokenTest extends TestCase
{
    private function span(int $start, int $end): Span
    {
        return new Span($start, $end);
    }

    #[Test]
    public function eof_creates_token_with_eof_type(): void
    {
        $token = Token::eof(10);

        $this->assertSame(TokenType::Eof, $token->type);
    }

    #[Test]
    public function eof_creates_token_with_empty_lexeme(): void
    {
        $token = Token::eof(10);

        $this->assertSame('', $token->lexeme);
    }

    #[Test]
    public function eof_creates_token_with_empty_span_at_offset(): void
    {
        $token = Token::eof(10);

        $this->assertSame(10, $token->span->start);
        $this->assertSame(10, $token->span->end);
        $this->assertTrue($token->span->isEmpty());
    }

    #[Test]
    public function invalid_creates_token_with_invalid_type(): void
    {
        $token = Token::invalid('$', $this->span(3, 4));

        $this->assertSame(TokenType::Invalid, $token->type);
    }

    #[Test]
    public function invalid_creates_token_with_correct_lexeme(): void
    {
        $token = Token::invalid('$', $this->span(3, 4));

        $this->assertSame('$', $token->lexeme);
    }

    #[Test]
    public function invalid_creates_token_with_correct_span(): void
    {
        $span  = $this->span(3, 4);
        $token = Token::invalid('$', $span);

        $this->assertSame($span, $token->span);
    }

    #[Test]
    public function is_returns_true_when_type_matches(): void
    {
        $token = new Token(TokenType::Integer, '42', $this->span(0, 2));

        $this->assertTrue($token->is(TokenType::Integer));
    }

    #[Test]
    public function is_returns_false_when_type_does_not_match(): void
    {
        $token = new Token(TokenType::Integer, '42', $this->span(0, 2));

        $this->assertFalse($token->is(TokenType::Float));
    }

    #[Test]
    public function is_any_returns_true_when_type_is_among_given(): void
    {
        $token = new Token(TokenType::Integer, '42', $this->span(0, 2));

        $this->assertTrue($token->isAny(TokenType::Float, TokenType::Integer, TokenType::Nan));
    }

    #[Test]
    public function is_any_returns_false_when_type_is_not_among_given(): void
    {
        $token = new Token(TokenType::Integer, '42', $this->span(0, 2));

        $this->assertFalse($token->isAny(TokenType::Float, TokenType::Nan));
    }

    #[Test]
    public function is_any_returns_false_with_empty_list(): void
    {
        $token = new Token(TokenType::Integer, '42', $this->span(0, 2));

        $this->assertFalse($token->isAny());
    }

    #[Test]
    public function is_trivia_returns_true_for_trivia_tokens(): void
    {
        $this->assertTrue((new Token(TokenType::Whitespace, ' ',    $this->span(0, 1)))->isTrivia());
        $this->assertTrue((new Token(TokenType::Newline,    "\n",   $this->span(0, 1)))->isTrivia());
        $this->assertTrue((new Token(TokenType::Comment,    '# hi', $this->span(0, 4)))->isTrivia());
    }

    #[Test]
    public function is_trivia_returns_false_for_non_trivia_tokens(): void
    {
        $this->assertFalse((new Token(TokenType::Integer, '1', $this->span(0, 1)))->isTrivia());
        $this->assertFalse((new Token(TokenType::Equals,  '=', $this->span(0, 1)))->isTrivia());
    }

    #[Test]
    public function is_key_returns_true_for_key_tokens(): void
    {
        $this->assertTrue((new Token(TokenType::BareKey,       'name',   $this->span(0, 4)))->isKey());
        $this->assertTrue((new Token(TokenType::BasicString,   '"name"', $this->span(0, 6)))->isKey());
        $this->assertTrue((new Token(TokenType::LiteralString, "'name'", $this->span(0, 6)))->isKey());
    }

    #[Test]
    public function is_key_returns_false_for_non_key_tokens(): void
    {
        $this->assertFalse((new Token(TokenType::Integer, '1', $this->span(0, 1)))->isKey());
        $this->assertFalse((new Token(TokenType::Equals,  '=', $this->span(0, 1)))->isKey());
    }

    #[Test]
    public function is_value_returns_true_for_value_tokens(): void
    {
        $this->assertTrue((new Token(TokenType::Integer,      '42',     $this->span(0, 2)))->isValue());
        $this->assertTrue((new Token(TokenType::BasicString,  '"hi"',   $this->span(0, 4)))->isValue());
        $this->assertTrue((new Token(TokenType::True,         'true',   $this->span(0, 4)))->isValue());
        $this->assertTrue((new Token(TokenType::Float,        '3.14',   $this->span(0, 4)))->isValue());
        $this->assertTrue((new Token(TokenType::LocalDate,    '1979-05-27', $this->span(0, 10)))->isValue());
    }

    #[Test]
    public function is_value_returns_false_for_non_value_tokens(): void
    {
        $this->assertFalse((new Token(TokenType::Equals,     '=',  $this->span(0, 1)))->isValue());
        $this->assertFalse((new Token(TokenType::Whitespace, ' ',  $this->span(0, 1)))->isValue());
        $this->assertFalse((new Token(TokenType::Eof,        '',   $this->span(0, 0)))->isValue());
    }

    #[Test]
    public function is_eof_returns_true_for_eof_token(): void
    {
        $this->assertTrue(Token::eof(0)->isEof());
    }

    #[Test]
    public function is_eof_returns_false_for_non_eof_tokens(): void
    {
        $this->assertFalse((new Token(TokenType::Integer, '1', $this->span(0, 1)))->isEof());
        $this->assertFalse((new Token(TokenType::Invalid, '$', $this->span(0, 1)))->isEof());
    }

    #[Test]
    public function to_string_includes_lexeme_for_literal_tokens(): void
    {
        $token = new Token(TokenType::Integer, '1_000', $this->span(8, 13));

        $this->assertSame('Token(Integer, "1_000", Span(8..13))', (string) $token);
    }

    #[Test]
    public function to_string_omits_lexeme_for_eof_token(): void
    {
        $this->assertSame('Token(Eof, Span(5..5))', (string) Token::eof(5));
    }
}
