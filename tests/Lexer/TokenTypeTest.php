<?php

declare(strict_types=1);

namespace PslToml\Tests\Lexer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PslToml\Lexer\TokenType;

final class TokenTypeTest extends TestCase
{
    #[Test]
    public function symbol_returns_correct_symbol_for_punctuation_tokens(): void
    {
        $this->assertSame('=',   TokenType::Equals->symbol());
        $this->assertSame('.',   TokenType::Dot->symbol());
        $this->assertSame(',',   TokenType::Comma->symbol());
        $this->assertSame(':',   TokenType::Colon->symbol());
        $this->assertSame('[',   TokenType::LeftBracket->symbol());
        $this->assertSame(']',   TokenType::RightBracket->symbol());
        $this->assertSame('[[',  TokenType::DoubleLeftBracket->symbol());
        $this->assertSame(']]',  TokenType::DoubleRightBracket->symbol());
        $this->assertSame('{',   TokenType::LeftBrace->symbol());
        $this->assertSame('}',   TokenType::RightBrace->symbol());
        $this->assertSame('true',  TokenType::True->symbol());
        $this->assertSame('false', TokenType::False->symbol());
        $this->assertSame('inf',   TokenType::Inf->symbol());
        $this->assertSame('nan',   TokenType::Nan->symbol());
        $this->assertSame('<eof>', TokenType::Eof->symbol());
    }

    #[Test]
    public function symbol_returns_null_for_variable_lexeme_tokens(): void
    {
        $this->assertNull(TokenType::BareKey->symbol());
        $this->assertNull(TokenType::BasicString->symbol());
        $this->assertNull(TokenType::MultiLineBasicString->symbol());
        $this->assertNull(TokenType::LiteralString->symbol());
        $this->assertNull(TokenType::MultiLineLiteralString->symbol());
        $this->assertNull(TokenType::Integer->symbol());
        $this->assertNull(TokenType::HexInteger->symbol());
        $this->assertNull(TokenType::OctalInteger->symbol());
        $this->assertNull(TokenType::BinaryInteger->symbol());
        $this->assertNull(TokenType::Float->symbol());
        $this->assertNull(TokenType::OffsetDateTime->symbol());
        $this->assertNull(TokenType::LocalDateTime->symbol());
        $this->assertNull(TokenType::LocalDate->symbol());
        $this->assertNull(TokenType::LocalTime->symbol());
        $this->assertNull(TokenType::Whitespace->symbol());
        $this->assertNull(TokenType::Newline->symbol());
        $this->assertNull(TokenType::Comment->symbol());
        $this->assertNull(TokenType::Invalid->symbol());
    }

    #[Test]
    public function label_returns_correct_label_for_all_tokens(): void
    {
        $this->assertSame("'='",  TokenType::Equals->label());
        $this->assertSame("'.'",  TokenType::Dot->label());
        $this->assertSame("','",  TokenType::Comma->label());
        $this->assertSame("':'",  TokenType::Colon->label());
        $this->assertSame("'['",  TokenType::LeftBracket->label());
        $this->assertSame("']'",  TokenType::RightBracket->label());
        $this->assertSame("'[['", TokenType::DoubleLeftBracket->label());
        $this->assertSame("']]'", TokenType::DoubleRightBracket->label());
        $this->assertSame("'{'",  TokenType::LeftBrace->label());
        $this->assertSame("'}'",  TokenType::RightBrace->label());
        $this->assertSame('<bare-key>',                TokenType::BareKey->label());
        $this->assertSame('<basic-string>',            TokenType::BasicString->label());
        $this->assertSame('<multi-line-basic-string>', TokenType::MultiLineBasicString->label());
        $this->assertSame('<literal-string>',          TokenType::LiteralString->label());
        $this->assertSame('<multi-line-literal-string>', TokenType::MultiLineLiteralString->label());
        $this->assertSame('<integer>',       TokenType::Integer->label());
        $this->assertSame('<hex-integer>',   TokenType::HexInteger->label());
        $this->assertSame('<octal-integer>', TokenType::OctalInteger->label());
        $this->assertSame('<binary-integer>', TokenType::BinaryInteger->label());
        $this->assertSame('<float>', TokenType::Float->label());
        $this->assertSame('<inf>',   TokenType::Inf->label());
        $this->assertSame('<nan>',   TokenType::Nan->label());
        $this->assertSame('true',  TokenType::True->label());
        $this->assertSame('false', TokenType::False->label());
        $this->assertSame('<offset-date-time>', TokenType::OffsetDateTime->label());
        $this->assertSame('<local-date-time>',  TokenType::LocalDateTime->label());
        $this->assertSame('<local-date>',       TokenType::LocalDate->label());
        $this->assertSame('<local-time>',       TokenType::LocalTime->label());
        $this->assertSame('<whitespace>', TokenType::Whitespace->label());
        $this->assertSame('<newline>',    TokenType::Newline->label());
        $this->assertSame('<comment>',    TokenType::Comment->label());
        $this->assertSame('<eof>',        TokenType::Eof->label());
        $this->assertSame('<invalid>',    TokenType::Invalid->label());
    }

    #[Test]
    public function is_literal_returns_true_for_literal_tokens(): void
    {
        $this->assertTrue(TokenType::BareKey->isLiteral());
        $this->assertTrue(TokenType::BasicString->isLiteral());
        $this->assertTrue(TokenType::MultiLineBasicString->isLiteral());
        $this->assertTrue(TokenType::LiteralString->isLiteral());
        $this->assertTrue(TokenType::MultiLineLiteralString->isLiteral());
        $this->assertTrue(TokenType::Integer->isLiteral());
        $this->assertTrue(TokenType::HexInteger->isLiteral());
        $this->assertTrue(TokenType::OctalInteger->isLiteral());
        $this->assertTrue(TokenType::BinaryInteger->isLiteral());
        $this->assertTrue(TokenType::Float->isLiteral());
        $this->assertTrue(TokenType::Inf->isLiteral());
        $this->assertTrue(TokenType::Nan->isLiteral());
        $this->assertTrue(TokenType::OffsetDateTime->isLiteral());
        $this->assertTrue(TokenType::LocalDateTime->isLiteral());
        $this->assertTrue(TokenType::LocalDate->isLiteral());
        $this->assertTrue(TokenType::LocalTime->isLiteral());
    }

    #[Test]
    public function is_literal_returns_false_for_non_literal_tokens(): void
    {
        $this->assertFalse(TokenType::Equals->isLiteral());
        $this->assertFalse(TokenType::True->isLiteral());
        $this->assertFalse(TokenType::False->isLiteral());
        $this->assertFalse(TokenType::Whitespace->isLiteral());
        $this->assertFalse(TokenType::Eof->isLiteral());
        $this->assertFalse(TokenType::Invalid->isLiteral());
    }

    #[Test]
    public function is_string_returns_true_for_string_tokens(): void
    {
        $this->assertTrue(TokenType::BasicString->isString());
        $this->assertTrue(TokenType::MultiLineBasicString->isString());
        $this->assertTrue(TokenType::LiteralString->isString());
        $this->assertTrue(TokenType::MultiLineLiteralString->isString());
    }

    #[Test]
    public function is_string_returns_false_for_non_string_tokens(): void
    {
        $this->assertFalse(TokenType::BareKey->isString());
        $this->assertFalse(TokenType::Integer->isString());
        $this->assertFalse(TokenType::Float->isString());
        $this->assertFalse(TokenType::True->isString());
        $this->assertFalse(TokenType::Eof->isString());
    }

    #[Test]
    public function is_integer_returns_true_for_integer_tokens(): void
    {
        $this->assertTrue(TokenType::Integer->isInteger());
        $this->assertTrue(TokenType::HexInteger->isInteger());
        $this->assertTrue(TokenType::OctalInteger->isInteger());
        $this->assertTrue(TokenType::BinaryInteger->isInteger());
    }

    #[Test]
    public function is_integer_returns_false_for_non_integer_tokens(): void
    {
        $this->assertFalse(TokenType::Float->isInteger());
        $this->assertFalse(TokenType::Inf->isInteger());
        $this->assertFalse(TokenType::Nan->isInteger());
        $this->assertFalse(TokenType::BasicString->isInteger());
        $this->assertFalse(TokenType::Eof->isInteger());
    }

    #[Test]
    public function is_numeric_returns_true_for_numeric_tokens(): void
    {
        $this->assertTrue(TokenType::Integer->isNumeric());
        $this->assertTrue(TokenType::HexInteger->isNumeric());
        $this->assertTrue(TokenType::OctalInteger->isNumeric());
        $this->assertTrue(TokenType::BinaryInteger->isNumeric());
        $this->assertTrue(TokenType::Float->isNumeric());
        $this->assertTrue(TokenType::Inf->isNumeric());
        $this->assertTrue(TokenType::Nan->isNumeric());
    }

    #[Test]
    public function is_numeric_returns_false_for_non_numeric_tokens(): void
    {
        $this->assertFalse(TokenType::BasicString->isNumeric());
        $this->assertFalse(TokenType::True->isNumeric());
        $this->assertFalse(TokenType::LocalDate->isNumeric());
        $this->assertFalse(TokenType::Eof->isNumeric());
    }

    #[Test]
    public function is_temporal_returns_true_for_temporal_tokens(): void
    {
        $this->assertTrue(TokenType::OffsetDateTime->isTemporal());
        $this->assertTrue(TokenType::LocalDateTime->isTemporal());
        $this->assertTrue(TokenType::LocalDate->isTemporal());
        $this->assertTrue(TokenType::LocalTime->isTemporal());
    }

    #[Test]
    public function is_temporal_returns_false_for_non_temporal_tokens(): void
    {
        $this->assertFalse(TokenType::Integer->isTemporal());
        $this->assertFalse(TokenType::BasicString->isTemporal());
        $this->assertFalse(TokenType::Eof->isTemporal());
    }

    #[Test]
    public function is_trivia_returns_true_for_trivia_tokens(): void
    {
        $this->assertTrue(TokenType::Whitespace->isTrivia());
        $this->assertTrue(TokenType::Newline->isTrivia());
        $this->assertTrue(TokenType::Comment->isTrivia());
    }

    #[Test]
    public function is_trivia_returns_false_for_non_trivia_tokens(): void
    {
        $this->assertFalse(TokenType::Equals->isTrivia());
        $this->assertFalse(TokenType::BareKey->isTrivia());
        $this->assertFalse(TokenType::Eof->isTrivia());
        $this->assertFalse(TokenType::Invalid->isTrivia());
    }

    #[Test]
    public function is_key_returns_true_for_key_tokens(): void
    {
        $this->assertTrue(TokenType::BareKey->isKey());
        $this->assertTrue(TokenType::BasicString->isKey());
        $this->assertTrue(TokenType::LiteralString->isKey());
    }

    #[Test]
    public function is_key_returns_false_for_non_key_tokens(): void
    {
        $this->assertFalse(TokenType::MultiLineBasicString->isKey());
        $this->assertFalse(TokenType::Integer->isKey());
        $this->assertFalse(TokenType::Equals->isKey());
        $this->assertFalse(TokenType::Eof->isKey());
    }

    #[Test]
    public function is_value_returns_true_for_value_tokens(): void
    {
        $this->assertTrue(TokenType::BasicString->isValue());
        $this->assertTrue(TokenType::MultiLineBasicString->isValue());
        $this->assertTrue(TokenType::LiteralString->isValue());
        $this->assertTrue(TokenType::MultiLineLiteralString->isValue());
        $this->assertTrue(TokenType::Integer->isValue());
        $this->assertTrue(TokenType::HexInteger->isValue());
        $this->assertTrue(TokenType::OctalInteger->isValue());
        $this->assertTrue(TokenType::BinaryInteger->isValue());
        $this->assertTrue(TokenType::Float->isValue());
        $this->assertTrue(TokenType::Inf->isValue());
        $this->assertTrue(TokenType::Nan->isValue());
        $this->assertTrue(TokenType::OffsetDateTime->isValue());
        $this->assertTrue(TokenType::LocalDateTime->isValue());
        $this->assertTrue(TokenType::LocalDate->isValue());
        $this->assertTrue(TokenType::LocalTime->isValue());
        $this->assertTrue(TokenType::True->isValue());
        $this->assertTrue(TokenType::False->isValue());
    }

    #[Test]
    public function is_value_returns_false_for_non_value_tokens(): void
    {
        $this->assertFalse(TokenType::Equals->isValue());
        $this->assertFalse(TokenType::BareKey->isValue());
        $this->assertFalse(TokenType::Whitespace->isValue());
        $this->assertFalse(TokenType::Comment->isValue());
        $this->assertFalse(TokenType::Eof->isValue());
        $this->assertFalse(TokenType::Invalid->isValue());
    }

    #[Test]
    public function filter_returns_only_matching_token_types(): void
    {
        $result = TokenType::filter(static fn(TokenType $t): bool => $t->isTrivia());

        $this->assertSame(
            [TokenType::Whitespace, TokenType::Newline, TokenType::Comment],
            $result,
        );
    }

    #[Test]
    public function filter_returns_empty_list_when_no_token_matches(): void
    {
        $result = TokenType::filter(static fn(TokenType $t): bool => false);

        $this->assertSame([], $result);
    }
}
