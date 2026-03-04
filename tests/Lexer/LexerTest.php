<?php

declare(strict_types=1);

namespace PslToml\Tests\Lexer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PslToml\Lexer\Lexer;
use PslToml\Lexer\TokenType;

final class LexerTest extends TestCase
{
    /**
     * @return list<TokenType>
     */
    private function tokenize(string $source): array
    {
        $lexer  = new Lexer($source);
        $tokens = [];

        foreach ($lexer->tokenize() as $token) {
            $tokens[] = $token->type;
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function lexemes(string $source): array
    {
        $lexer   = new Lexer($source);
        $lexemes = [];

        foreach ($lexer->tokenize() as $token) {
            $lexemes[] = $token->lexeme;
        }

        return $lexemes;
    }

    #[Test]
    public function it_tokenizes_equals(): void
    {
        $this->assertSame([TokenType::Equals, TokenType::Eof], $this->tokenize('='));
    }

    #[Test]
    public function it_tokenizes_dot(): void
    {
        $this->assertSame([TokenType::Dot, TokenType::Eof], $this->tokenize('.'));
    }

    #[Test]
    public function it_tokenizes_comma(): void
    {
        $this->assertSame([TokenType::Comma, TokenType::Eof], $this->tokenize(','));
    }

    #[Test]
    public function it_tokenizes_colon(): void
    {
        $this->assertSame([TokenType::Colon, TokenType::Eof], $this->tokenize(':'));
    }

    #[Test]
    public function it_tokenizes_left_brace(): void
    {
        $this->assertSame([TokenType::LeftBrace, TokenType::Eof], $this->tokenize('{'));
    }

    #[Test]
    public function it_tokenizes_right_brace(): void
    {
        $this->assertSame([TokenType::RightBrace, TokenType::Eof], $this->tokenize('}'));
    }

    #[Test]
    public function it_tokenizes_left_bracket(): void
    {
        $this->assertSame([TokenType::LeftBracket, TokenType::Eof], $this->tokenize('['));
    }

    #[Test]
    public function it_tokenizes_right_bracket(): void
    {
        $this->assertSame([TokenType::RightBracket, TokenType::Eof], $this->tokenize(']'));
    }

    #[Test]
    public function it_tokenizes_double_left_bracket(): void
    {
        $this->assertSame([TokenType::DoubleLeftBracket, TokenType::Eof], $this->tokenize('[['));
    }

    #[Test]
    public function it_tokenizes_double_right_bracket(): void
    {
        $this->assertSame([TokenType::DoubleRightBracket, TokenType::Eof], $this->tokenize(']]'));
    }

    #[Test]
    public function it_tokenizes_spaces_as_whitespace(): void
    {
        $this->assertSame([TokenType::Whitespace, TokenType::Eof], $this->tokenize('   '));
    }

    #[Test]
    public function it_tokenizes_tabs_as_whitespace(): void
    {
        $this->assertSame([TokenType::Whitespace, TokenType::Eof], $this->tokenize("\t\t"));
    }

    #[Test]
    public function it_tokenizes_mixed_spaces_and_tabs_as_single_whitespace(): void
    {
        $this->assertSame([TokenType::Whitespace, TokenType::Eof], $this->tokenize(" \t "));
    }

    #[Test]
    public function it_tokenizes_lf_as_newline(): void
    {
        $this->assertSame([TokenType::Newline, TokenType::Eof], $this->tokenize("\n"));
    }

    #[Test]
    public function it_tokenizes_crlf_as_newline(): void
    {
        $this->assertSame([TokenType::Newline, TokenType::Eof], $this->tokenize("\r\n"));
    }

    #[Test]
    public function it_tokenizes_bare_cr_as_invalid(): void
    {
        $this->assertSame([TokenType::Invalid, TokenType::Eof], $this->tokenize("\r"));
    }

    #[Test]
    public function it_tokenizes_comment(): void
    {
        $this->assertSame([TokenType::Comment, TokenType::Eof], $this->tokenize('# hello world'));
    }

    #[Test]
    public function it_does_not_include_newline_in_comment(): void
    {
        $types = $this->tokenize("# hello\n");

        $this->assertSame([TokenType::Comment, TokenType::Newline, TokenType::Eof], $types);
    }

    #[Test]
    public function it_tokenizes_basic_string(): void
    {
        $this->assertSame([TokenType::BasicString, TokenType::Eof], $this->tokenize('"hello"'));
    }

    #[Test]
    public function it_tokenizes_basic_string_with_escape(): void
    {
        $this->assertSame([TokenType::BasicString, TokenType::Eof], $this->tokenize('"hello\\nworld"'));
    }

    #[Test]
    public function it_tokenizes_multi_line_basic_string(): void
    {
        $this->assertSame([TokenType::MultiLineBasicString, TokenType::Eof], $this->tokenize('"""hello"""'));
    }

    #[Test]
    public function it_tokenizes_literal_string(): void
    {
        $this->assertSame([TokenType::LiteralString, TokenType::Eof], $this->tokenize("'hello'"));
    }

    #[Test]
    public function it_tokenizes_multi_line_literal_string(): void
    {
        $this->assertSame([TokenType::MultiLineLiteralString, TokenType::Eof], $this->tokenize("'''hello'''"));
    }

    #[Test]
    public function it_tokenizes_decimal_integer(): void
    {
        $this->assertSame([TokenType::Integer, TokenType::Eof], $this->tokenize('42'));
    }

    #[Test]
    public function it_tokenizes_decimal_integer_with_underscores(): void
    {
        $this->assertSame([TokenType::Integer, TokenType::Eof], $this->tokenize('1_000_000'));
    }

    #[Test]
    public function it_tokenizes_hex_integer(): void
    {
        $this->assertSame([TokenType::HexInteger, TokenType::Eof], $this->tokenize('0xFF'));
    }

    #[Test]
    public function it_tokenizes_octal_integer(): void
    {
        $this->assertSame([TokenType::OctalInteger, TokenType::Eof], $this->tokenize('0o755'));
    }

    #[Test]
    public function it_tokenizes_binary_integer(): void
    {
        $this->assertSame([TokenType::BinaryInteger, TokenType::Eof], $this->tokenize('0b1010'));
    }

    #[Test]
    public function it_tokenizes_float_with_decimal(): void
    {
        $this->assertSame([TokenType::Float, TokenType::Eof], $this->tokenize('3.14'));
    }

    #[Test]
    public function it_tokenizes_float_with_exponent(): void
    {
        $this->assertSame([TokenType::Float, TokenType::Eof], $this->tokenize('6.626e-34'));
    }

    #[Test]
    public function it_tokenizes_inf(): void
    {
        $this->assertSame([TokenType::Inf, TokenType::Eof], $this->tokenize('inf'));
    }

    #[Test]
    public function it_tokenizes_positive_inf(): void
    {
        $this->assertSame([TokenType::Inf, TokenType::Eof], $this->tokenize('+inf'));
    }

    #[Test]
    public function it_tokenizes_negative_inf(): void
    {
        $this->assertSame([TokenType::Inf, TokenType::Eof], $this->tokenize('-inf'));
    }

    #[Test]
    public function it_tokenizes_nan(): void
    {
        $this->assertSame([TokenType::Nan, TokenType::Eof], $this->tokenize('nan'));
    }

    #[Test]
    public function it_tokenizes_positive_nan(): void
    {
        $this->assertSame([TokenType::Nan, TokenType::Eof], $this->tokenize('+nan'));
    }

    #[Test]
    public function it_tokenizes_negative_nan(): void
    {
        $this->assertSame([TokenType::Nan, TokenType::Eof], $this->tokenize('-nan'));
    }

    #[Test]
    public function it_tokenizes_true(): void
    {
        $this->assertSame([TokenType::True, TokenType::Eof], $this->tokenize('true'));
    }

    #[Test]
    public function it_tokenizes_false(): void
    {
        $this->assertSame([TokenType::False, TokenType::Eof], $this->tokenize('false'));
    }

    #[Test]
    public function it_tokenizes_offset_date_time(): void
    {
        $this->assertSame([TokenType::OffsetDateTime, TokenType::Eof], $this->tokenize('1979-05-27T07:32:00Z'));
    }

    #[Test]
    public function it_tokenizes_offset_date_time_with_offset(): void
    {
        $this->assertSame([TokenType::OffsetDateTime, TokenType::Eof], $this->tokenize('1979-05-27T07:32:00+02:00'));
    }

    #[Test]
    public function it_tokenizes_local_date_time(): void
    {
        $this->assertSame([TokenType::LocalDateTime, TokenType::Eof], $this->tokenize('1979-05-27T07:32:00'));
    }

    #[Test]
    public function it_tokenizes_local_date(): void
    {
        $this->assertSame([TokenType::LocalDate, TokenType::Eof], $this->tokenize('1979-05-27'));
    }

    #[Test]
    public function it_tokenizes_local_time(): void
    {
        $this->assertSame([TokenType::LocalTime, TokenType::Eof], $this->tokenize('07:32:00'));
    }

    #[Test]
    public function it_tokenizes_local_time_with_subseconds(): void
    {
        $this->assertSame([TokenType::LocalTime, TokenType::Eof], $this->tokenize('07:32:00.999999'));
    }

    #[Test]
    public function it_tokenizes_bare_key(): void
    {
        $this->assertSame([TokenType::BareKey, TokenType::Eof], $this->tokenize('name'));
    }

    #[Test]
    public function it_tokenizes_bare_key_with_underscores_and_dashes(): void
    {
        $this->assertSame([TokenType::BareKey, TokenType::Eof], $this->tokenize('my-key_name'));
    }

    #[Test]
    public function it_tokenizes_keyword_followed_by_bare_key_char_as_bare_key(): void
    {
        $this->assertSame([TokenType::BareKey, TokenType::Eof], $this->tokenize('truex'));
    }

    #[Test]
    public function it_tokenizes_false_followed_by_bare_key_char_as_bare_key(): void
    {
        $this->assertSame([TokenType::BareKey, TokenType::Eof], $this->tokenize('false1'));
    }

    #[Test]
    public function it_tokenizes_unrecognised_character_as_invalid(): void
    {
        $this->assertSame([TokenType::Invalid, TokenType::Eof], $this->tokenize('$'));
    }

    #[Test]
    public function it_always_ends_with_eof(): void
    {
        $last = null;

        foreach ((new Lexer('name = "toml"'))->tokenize() as $token) {
            $last = $token;
        }

        $this->assertNotNull($last);
        $this->assertTrue($last->isEof());
    }

    #[Test]
    public function it_tokenizes_single_space_as_whitespace_and_eof(): void
    {
        $this->assertSame([TokenType::Whitespace, TokenType::Eof], $this->tokenize(' '));
    }

    #[Test]
    public function it_produces_correct_lexeme_for_each_token(): void
    {
        $lexemes = $this->lexemes('name = "toml"');

        $this->assertSame(['name', ' ', '=', ' ', '"toml"', ''], $lexemes);
    }

    #[Test]
    public function it_produces_correct_span_for_each_token(): void
    {
        $lexer  = new Lexer('name = 42');
        $spans  = [];

        foreach ($lexer->tokenize() as $token) {
            $spans[] = (string) $token->span;
        }

        $this->assertSame([
            'Span(0..4)',
            'Span(4..5)',
            'Span(5..6)',
            'Span(6..7)',
            'Span(7..9)',
            'Span(9..9)',
        ], $spans);
    }

    #[Test]
    public function it_tokenizes_a_key_value_pair(): void
    {
        $types = $this->tokenize('name = "toml"');

        $this->assertSame([
            TokenType::BareKey,
            TokenType::Whitespace,
            TokenType::Equals,
            TokenType::Whitespace,
            TokenType::BasicString,
            TokenType::Eof,
        ], $types);
    }

    #[Test]
    public function it_tokenizes_a_table_header(): void
    {
        $types = $this->tokenize('[database]');

        $this->assertSame([
            TokenType::LeftBracket,
            TokenType::BareKey,
            TokenType::RightBracket,
            TokenType::Eof,
        ], $types);
    }

    #[Test]
    public function it_tokenizes_an_array_of_tables_header(): void
    {
        $types = $this->tokenize('[[products]]');

        $this->assertSame([
            TokenType::DoubleLeftBracket,
            TokenType::BareKey,
            TokenType::DoubleRightBracket,
            TokenType::Eof,
        ], $types);
    }

    #[Test]
    public function it_tokenizes_an_inline_table(): void
    {
        $types = $this->tokenize('{name = "toml"}');

        $this->assertSame([
            TokenType::LeftBrace,
            TokenType::BareKey,
            TokenType::Whitespace,
            TokenType::Equals,
            TokenType::Whitespace,
            TokenType::BasicString,
            TokenType::RightBrace,
            TokenType::Eof,
        ], $types);
    }

    #[Test]
    public function it_tokenizes_multi_line_basic_string_with_escape_sequence(): void
    {
        $this->assertSame([TokenType::MultiLineBasicString, TokenType::Eof], $this->tokenize('"""hello\\nworld"""'));
    }

    #[Test]
    public function it_tokenizes_positive_integer(): void
    {
        $this->assertSame([TokenType::Integer, TokenType::Eof], $this->tokenize('+42'));
    }

    #[Test]
    public function it_tokenizes_negative_integer(): void
    {
        $this->assertSame([TokenType::Integer, TokenType::Eof], $this->tokenize('-42'));
    }

    #[Test]
    public function it_tokenizes_sign_without_number_as_invalid(): void
    {
        $this->assertSame([TokenType::Invalid, TokenType::Eof], $this->tokenize('+'));
    }

    #[Test]
    public function it_tokenizes_minus_without_number_as_invalid(): void
    {
        $this->assertSame([TokenType::Invalid, TokenType::Eof], $this->tokenize('-'));
    }

    #[Test]
    public function it_tokenizes_double_quote_at_end_of_source_without_panic(): void
    {
        $this->assertSame([TokenType::BasicString, TokenType::Eof], $this->tokenize('""'));
    }

    #[Test]
    public function it_tokenizes_single_quote_at_end_of_source_without_panic(): void
    {
        $this->assertSame([TokenType::LiteralString, TokenType::Eof], $this->tokenize("''"));
    }
}
