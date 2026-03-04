<?php

declare(strict_types=1);

namespace PslToml\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Result\Failure;
use Psl\Result\Success;
use Psl\Type;
use PslToml\Document;
use PslToml\Exception\ParseException;
use PslToml\Parser;

final class ParserTest extends TestCase
{
    private function parse(string $source): Document
    {
        $result = (new Parser($source))->parse();

        $this->assertInstanceOf(Success::class, $result);

        return $result->getResult();
    }

    private function parseFailure(string $source): ParseException
    {
        $result = (new Parser($source))->parse();

        $this->assertInstanceOf(Failure::class, $result);

        return $result->getThrowable();
    }

    // -------------------------------------------------------------------------
    // Result wrapping
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_returns_success_for_valid_input(): void
    {
        $result = (new Parser('name = "Alice"'))->parse();

        $this->assertInstanceOf(Success::class, $result);
    }

    #[Test]
    public function parse_returns_failure_for_invalid_input(): void
    {
        $result = (new Parser('name = $invalid'))->parse();

        $this->assertInstanceOf(Failure::class, $result);
    }

    #[Test]
    public function parse_failure_contains_parse_exception(): void
    {
        $exception = $this->parseFailure('name = $invalid');

        $this->assertInstanceOf(ParseException::class, $exception);
    }

    // -------------------------------------------------------------------------
    // Empty document
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_produces_empty_document_for_whitespace_only(): void
    {
        $doc = $this->parse("   \n   \n");

        $this->assertSame([], $doc->keys());
    }

    #[Test]
    public function parse_produces_empty_document_for_comment_only(): void
    {
        $doc = $this->parse('# this is a comment');

        $this->assertSame([], $doc->keys());
    }

    // -------------------------------------------------------------------------
    // Scalar values
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_basic_string(): void
    {
        $doc = $this->parse('name = "Alice"');

        $this->assertSame('Alice', $doc->get('name', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_escape_sequences(): void
    {
        $doc = $this->parse('value = "line1\nline2\ttabbed"');

        $this->assertSame("line1\nline2\ttabbed", $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_unicode_escape(): void
    {
        $doc = $this->parse('value = "\u0041"');

        $this->assertSame('A', $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_literal_string(): void
    {
        $doc = $this->parse("value = 'C:\\Users\\file'");

        $this->assertSame('C:\\Users\\file', $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_multiline_basic_string(): void
    {
        $doc = $this->parse("value = \"\"\"\nline1\nline2\"\"\"");

        $this->assertSame("line1\nline2", $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_multiline_literal_string(): void
    {
        $doc = $this->parse("value = '''\nraw\\nno-escape'''");

        $this->assertSame('raw\\nno-escape', $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_decimal_integer(): void
    {
        $doc = $this->parse('port = 5432');

        $this->assertSame(5432, $doc->get('port', Type\int())->unwrap());
    }

    #[Test]
    public function parse_negative_integer(): void
    {
        $doc = $this->parse('offset = -17');

        $this->assertSame(-17, $doc->get('offset', Type\int())->unwrap());
    }

    #[Test]
    public function parse_integer_with_underscores(): void
    {
        $doc = $this->parse('count = 1_000_000');

        $this->assertSame(1_000_000, $doc->get('count', Type\int())->unwrap());
    }

    #[Test]
    public function parse_hex_integer(): void
    {
        $doc = $this->parse('value = 0xFF');

        $this->assertSame(255, $doc->get('value', Type\int())->unwrap());
    }

    #[Test]
    public function parse_octal_integer(): void
    {
        $doc = $this->parse('value = 0o17');

        $this->assertSame(15, $doc->get('value', Type\int())->unwrap());
    }

    #[Test]
    public function parse_binary_integer(): void
    {
        $doc = $this->parse('value = 0b1010');

        $this->assertSame(10, $doc->get('value', Type\int())->unwrap());
    }

    #[Test]
    public function parse_float(): void
    {
        $doc = $this->parse('pi = 3.14');

        $this->assertEqualsWithDelta(3.14, $doc->get('pi', Type\float())->unwrap(), 0.0001);
    }

    #[Test]
    public function parse_float_with_exponent(): void
    {
        $doc = $this->parse('value = 6.626e-34');

        $this->assertEqualsWithDelta(6.626e-34, $doc->get('value', Type\float())->unwrap(), 1e-37);
    }

    #[Test]
    public function parse_positive_inf(): void
    {
        $doc = $this->parse('value = inf');

        $this->assertSame(INF, $doc->get('value', Type\float())->unwrap());
    }

    #[Test]
    public function parse_negative_inf(): void
    {
        $doc = $this->parse('value = -inf');

        $this->assertSame(-INF, $doc->get('value', Type\float())->unwrap());
    }

    #[Test]
    public function parse_nan(): void
    {
        $doc = $this->parse('value = nan');

        $this->assertNan($doc->get('value', Type\float())->unwrap());
    }

    #[Test]
    public function parse_true(): void
    {
        $doc = $this->parse('enabled = true');

        $this->assertTrue($doc->get('enabled', Type\bool())->unwrap());
    }

    #[Test]
    public function parse_false(): void
    {
        $doc = $this->parse('enabled = false');

        $this->assertFalse($doc->get('enabled', Type\bool())->unwrap());
    }

    // -------------------------------------------------------------------------
    // Date / time
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_offset_datetime(): void
    {
        $doc = $this->parse('dt = 1979-05-27T07:32:00Z');

        $dt = $doc->get('dt', Type\instance_of(\DateTimeImmutable::class))->unwrap();

        $this->assertSame('1979-05-27', $dt->format('Y-m-d'));
        $this->assertSame('07:32:00', $dt->format('H:i:s'));
    }

    #[Test]
    public function parse_local_datetime(): void
    {
        $doc = $this->parse('dt = 1979-05-27T07:32:00');

        $dt = $doc->get('dt', Type\instance_of(\DateTimeImmutable::class))->unwrap();

        $this->assertSame('1979-05-27 07:32:00', $dt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function parse_local_date(): void
    {
        $doc = $this->parse('d = 1979-05-27');

        $dt = $doc->get('d', Type\instance_of(\DateTimeImmutable::class))->unwrap();

        $this->assertSame('1979-05-27', $dt->format('Y-m-d'));
    }

    #[Test]
    public function parse_local_time(): void
    {
        $doc = $this->parse('t = 07:32:00');

        $dt = $doc->get('t', Type\instance_of(\DateTimeImmutable::class))->unwrap();

        $this->assertSame('07:32:00', $dt->format('H:i:s'));
    }

    // -------------------------------------------------------------------------
    // Arrays
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_array_of_integers(): void
    {
        $doc = $this->parse('ports = [8080, 8443, 9000]');

        $this->assertSame([8080, 8443, 9000], $doc->get('ports', Type\vec(Type\int()))->unwrap());
    }

    #[Test]
    public function parse_empty_array(): void
    {
        $doc = $this->parse('items = []');

        $this->assertSame([], $doc->get('items', Type\vec(Type\mixed()))->unwrap());
    }

    #[Test]
    public function parse_array_with_trailing_comma(): void
    {
        $doc = $this->parse('items = [1, 2, 3,]');

        $this->assertSame([1, 2, 3], $doc->get('items', Type\vec(Type\int()))->unwrap());
    }

    // -------------------------------------------------------------------------
    // Inline tables
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_inline_table(): void
    {
        $doc = $this->parse('point = { x = 1, y = 2 }');

        $table = $doc->table('point')->unwrap();

        $this->assertSame(1, $table->get('x', Type\int())->unwrap());
        $this->assertSame(2, $table->get('y', Type\int())->unwrap());
    }

    #[Test]
    public function parse_inline_table_with_trailing_comma(): void
    {
        $doc = $this->parse('point = { x = 1, y = 2, }');

        $table = $doc->table('point')->unwrap();

        $this->assertSame(1, $table->get('x', Type\int())->unwrap());
        $this->assertSame(2, $table->get('y', Type\int())->unwrap());
    }

    #[Test]
    public function parse_inline_table_spanning_multiple_lines(): void
    {
        $doc = $this->parse("point = {\n  x = 1,\n  y = 2\n}");

        $table = $doc->table('point')->unwrap();

        $this->assertSame(1, $table->get('x', Type\int())->unwrap());
        $this->assertSame(2, $table->get('y', Type\int())->unwrap());
    }

    // -------------------------------------------------------------------------
    // Keys
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_unicode_bare_key(): void
    {
        $doc = $this->parse('résumé = "Alice"');

        $this->assertSame('Alice', $doc->get('résumé', Type\string())->unwrap());
    }

    #[Test]
    public function parse_cjk_bare_key(): void
    {
        $doc = $this->parse('日本語 = "Japanese"');

        $this->assertSame('Japanese', $doc->get('日本語', Type\string())->unwrap());
    }

    #[Test]
    public function parse_unicode_bare_key_in_table_header(): void
    {
        $doc = $this->parse("[配置]\nhost = \"localhost\"");

        $this->assertSame('localhost', $doc->get('配置.host', Type\string())->unwrap());
    }

    #[Test]
    public function parse_quoted_key(): void
    {
        $doc = $this->parse('"my key" = "value"');

        $this->assertSame('value', $doc->get('my key', Type\string())->unwrap());
    }

    #[Test]
    public function parse_dotted_key(): void
    {
        $doc = $this->parse('server.host = "localhost"');

        $this->assertSame('localhost', $doc->get('server.host', Type\string())->unwrap());
    }

    #[Test]
    public function parse_dotted_key_creates_nested_table(): void
    {
        $doc = $this->parse("server.host = \"localhost\"\nserver.port = 8080");

        $this->assertSame('localhost', $doc->get('server.host', Type\string())->unwrap());
        $this->assertSame(8080, $doc->get('server.port', Type\int())->unwrap());
    }

    // -------------------------------------------------------------------------
    // Tables
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_table_header(): void
    {
        $doc = $this->parse("[database]\nhost = \"localhost\"\nport = 5432");

        $this->assertSame('localhost', $doc->get('database.host', Type\string())->unwrap());
        $this->assertSame(5432, $doc->get('database.port', Type\int())->unwrap());
    }

    #[Test]
    public function parse_multiple_tables(): void
    {
        $source = "[server]\nhost = \"0.0.0.0\"\n\n[database]\nport = 5432";
        $doc    = $this->parse($source);

        $this->assertSame('0.0.0.0', $doc->get('server.host', Type\string())->unwrap());
        $this->assertSame(5432, $doc->get('database.port', Type\int())->unwrap());
    }

    #[Test]
    public function parse_table_with_dotted_key_inside(): void
    {
        $doc = $this->parse("[server]\nhost.address = \"127.0.0.1\"");

        $this->assertSame('127.0.0.1', $doc->get('server.host.address', Type\string())->unwrap());
    }

    // -------------------------------------------------------------------------
    // Array of tables
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_array_of_tables(): void
    {
        $source = "[[products]]\nname = \"Hammer\"\n\n[[products]]\nname = \"Nail\"";
        $doc    = $this->parse($source);

        $products = $doc->get('products', Type\vec(Type\dict(Type\string(), Type\mixed())))->unwrap();

        $this->assertCount(2, $products);
        $this->assertSame('Hammer', $products[0]['name']);
        $this->assertSame('Nail', $products[1]['name']);
    }

    #[Test]
    public function parse_array_of_tables_with_multiple_fields(): void
    {
        $source = "[[servers]]\nip = \"10.0.0.1\"\nport = 80\n\n[[servers]]\nip = \"10.0.0.2\"\nport = 443";
        $doc    = $this->parse($source);

        $servers = $doc->get('servers', Type\vec(Type\dict(Type\string(), Type\mixed())))->unwrap();

        $this->assertSame('10.0.0.1', $servers[0]['ip']);
        $this->assertSame(80, $servers[0]['port']);
        $this->assertSame('10.0.0.2', $servers[1]['ip']);
        $this->assertSame(443, $servers[1]['port']);
    }

    // -------------------------------------------------------------------------
    // Nested array of tables
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_nested_array_of_tables_with_prefix_path(): void
    {
        $source = "[[fruits.varieties]]\nname = \"red delicious\"\n\n[[fruits.varieties]]\nname = \"granny smith\"";
        $doc    = $this->parse($source);

        $varieties = $doc->get('fruits.varieties', Type\vec(Type\dict(Type\string(), Type\mixed())))->unwrap();

        $this->assertCount(2, $varieties);
        $this->assertSame('red delicious', $varieties[0]['name']);
        $this->assertSame('granny smith', $varieties[1]['name']);
    }

    // -------------------------------------------------------------------------
    // Quoted keys in table headers
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_literal_string_key_in_table_header(): void
    {
        $doc = $this->parse("['database']\nhost = \"localhost\"");

        $this->assertSame('localhost', $doc->get('database.host', Type\string())->unwrap());
    }

    // -------------------------------------------------------------------------
    // Escape sequences
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_basic_string_with_backspace_escape(): void
    {
        $doc = $this->parse('value = "\b"');

        $this->assertSame("\x08", $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_form_feed_escape(): void
    {
        $doc = $this->parse('value = "\f"');

        $this->assertSame("\x0C", $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_carriage_return_escape(): void
    {
        $doc = $this->parse('value = "\r"');

        $this->assertSame("\r", $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_double_quote_escape(): void
    {
        $doc = $this->parse('value = "\""');

        $this->assertSame('"', $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_backslash_escape(): void
    {
        $doc = $this->parse('value = "\\\\"');

        $this->assertSame('\\', $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_esc_escape(): void
    {
        $doc = $this->parse('value = "\e"');

        $this->assertSame("\x1B", $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_multiline_basic_string_with_esc_escape(): void
    {
        $doc = $this->parse("value = \"\"\"\n\\e\"\"\"");

        $this->assertSame("\x1B", $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_large_unicode_escape(): void
    {
        $doc = $this->parse('value = "\U0001F600"');

        $this->assertSame('😀', $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_preserves_unknown_escape_sequence(): void
    {
        $doc = $this->parse('value = "\z"');

        $this->assertSame('\z', $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_basic_string_with_trailing_backslash_at_end_of_source(): void
    {
        // Source ends with \" — lexer consumes the closing " as part of the escape,
        // leaving the inner string ending with \, which exercises the defensive break
        // in decodeEscapes when the backslash has no following character.
        $doc = $this->parse('value = "abc\\"');

        $this->assertSame('abc', $doc->get('value', Type\string())->unwrap());
    }

    // -------------------------------------------------------------------------
    // CRLF in multiline strings
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_multiline_basic_string_with_crlf(): void
    {
        $doc = $this->parse("value = \"\"\"\r\nline1\r\nline2\"\"\"");

        $this->assertSame("line1\r\nline2", $doc->get('value', Type\string())->unwrap());
    }

    #[Test]
    public function parse_multiline_literal_string_with_crlf(): void
    {
        $doc = $this->parse("value = '''\r\nraw content'''");

        $this->assertSame('raw content', $doc->get('value', Type\string())->unwrap());
    }

    // -------------------------------------------------------------------------
    // Error cases
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_error_on_unexpected_token_at_document_level(): void
    {
        $exception = $this->parseFailure('= "value"');

        $this->assertStringContainsString('unexpected token', $exception->getMessage());
    }

    #[Test]
    public function parse_error_on_invalid_key_token(): void
    {
        $exception = $this->parseFailure('a.= "value"');

        $this->assertStringContainsString('expected key', $exception->getMessage());
    }

    #[Test]
    public function parse_error_on_eof_in_value_position(): void
    {
        $exception = $this->parseFailure('key = ');

        $this->assertStringContainsString('unexpected end of file', $exception->getMessage());
    }

    #[Test]
    public function parse_error_on_invalid_character_in_value(): void
    {
        $exception = $this->parseFailure('name = $invalid');

        $this->assertStringContainsString('invalid character', $exception->getMessage());
    }

    #[Test]
    public function parse_error_on_missing_equals(): void
    {
        $exception = $this->parseFailure('name "value"');

        $this->assertStringContainsString("expected '='", $exception->getMessage());
    }

    #[Test]
    public function parse_error_on_unclosed_table_header(): void
    {
        $exception = $this->parseFailure('[database');

        $this->assertStringContainsString("expected ']'", $exception->getMessage());
    }

    #[Test]
    public function parse_error_reports_correct_position(): void
    {
        $exception = $this->parseFailure("name = \"ok\"\nbad = $");

        $this->assertStringContainsString('line 2', $exception->getMessage());
    }
}
