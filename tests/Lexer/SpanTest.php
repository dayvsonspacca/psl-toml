<?php

declare(strict_types=1);

namespace PslToml\Tests\Lexer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type\Exception\AssertException;
use PslToml\Lexer\Span;

final class SpanTest extends TestCase
{
    #[Test]
    public function it_creates_a_valid_span(): void
    {
        $span = new Span(7, 13);

        $this->assertSame(7, $span->start);
        $this->assertSame(13, $span->end);
    }

    #[Test]
    public function it_accepts_zero_as_start_and_end(): void
    {
        $span = new Span(0, 0);

        $this->assertSame(0, $span->start);
        $this->assertSame(0, $span->end);
    }

    #[Test]
    public function it_rejects_negative_start(): void
    {
        $this->expectException(AssertException::class);

        new Span(-1, 5);
    }

    #[Test]
    public function it_rejects_negative_end(): void
    {
        $this->expectException(AssertException::class);

        new Span(0, -1);
    }

    #[Test]
    public function it_rejects_start_greater_than_end(): void
    {
        $this->expectException(AssertException::class);

        new Span(10, 5);
    }

    #[Test]
    public function empty_span_has_equal_start_and_end(): void
    {
        $span = Span::empty(42);

        $this->assertSame(42, $span->start);
        $this->assertSame(42, $span->end);
    }

    #[Test]
    public function empty_span_is_empty(): void
    {
        $this->assertTrue(Span::empty(0)->isEmpty());
    }

    #[Test]
    public function empty_span_has_length_zero(): void
    {
        $this->assertSame(0, Span::empty(7)->length());
    }

    #[Test]
    public function length_is_end_minus_start(): void
    {
        $this->assertSame(6, (new Span(7, 13))->length());
    }

    #[Test]
    public function is_empty_returns_true_when_start_equals_end(): void
    {
        $this->assertTrue((new Span(5, 5))->isEmpty());
    }

    #[Test]
    public function is_empty_returns_false_when_start_less_than_end(): void
    {
        $this->assertFalse((new Span(5, 6))->isEmpty());
    }

    #[Test]
    public function contains_returns_true_when_other_is_completely_inside(): void
    {
        $outer = new Span(0, 10);
        $inner = new Span(2, 8);

        $this->assertTrue($outer->contains($inner));
    }

    #[Test]
    public function contains_returns_true_for_identical_spans(): void
    {
        $span = new Span(3, 7);

        $this->assertTrue($span->contains($span));
    }

    #[Test]
    public function contains_returns_false_when_other_exceeds_end(): void
    {
        $span  = new Span(0, 5);
        $other = new Span(3, 8);

        $this->assertFalse($span->contains($other));
    }

    #[Test]
    public function contains_returns_false_when_other_starts_before_start(): void
    {
        $span  = new Span(5, 10);
        $other = new Span(3, 8);

        $this->assertFalse($span->contains($other));
    }

    #[Test]
    public function merge_covers_the_two_spans(): void
    {
        $a      = new Span(2, 5);
        $b      = new Span(8, 12);
        $merged = $a->merge($b);

        $this->assertSame(2, $merged->start);
        $this->assertSame(12, $merged->end);
    }

    #[Test]
    public function merge_works_with_adjacent_spans(): void
    {
        $a      = new Span(0, 5);
        $b      = new Span(5, 10);
        $merged = $a->merge($b);

        $this->assertSame(0, $merged->start);
        $this->assertSame(10, $merged->end);
    }

    #[Test]
    public function merge_works_with_overlapping_spans(): void
    {
        $a      = new Span(0, 8);
        $b      = new Span(5, 12);
        $merged = $a->merge($b);

        $this->assertSame(0, $merged->start);
        $this->assertSame(12, $merged->end);
    }

    #[Test]
    public function merge_works_when_one_contains_the_other(): void
    {
        $outer  = new Span(0, 20);
        $inner  = new Span(5, 10);
        $merged = $outer->merge($inner);

        $this->assertSame(0, $merged->start);
        $this->assertSame(20, $merged->end);
    }

    #[Test]
    public function slice_returns_correct_bytes_from_source(): void
    {
        $source = 'name = "toml"';
        $span   = new Span(7, 13);

        $this->assertSame('"toml"', $span->slice($source));
    }

    #[Test]
    public function slice_returns_empty_string_for_empty_span(): void
    {
        $span = Span::empty(3);

        $this->assertSame('', $span->slice('name = "toml"'));
    }

    #[Test]
    public function to_line_column_returns_one_one_for_offset_zero(): void
    {
        $span   = new Span(0, 1);
        $result = $span->toLineColumn('x');

        $this->assertSame(1, $result['line']);
        $this->assertSame(1, $result['column']);
    }

    #[Test]
    public function to_line_column_increments_column_correctly(): void
    {
        $source = 'name = "toml"';
        $span   = new Span(7, 8);
        $result = $span->toLineColumn($source);

        $this->assertSame(1, $result['line']);
        $this->assertSame(8, $result['column']);
    }

    #[Test]
    public function to_line_column_increments_line_on_newline(): void
    {
        $source = "name = \"toml\"\nversion = 1";
        $span   = new Span(14, 21);
        $result = $span->toLineColumn($source);

        $this->assertSame(2, $result['line']);
        $this->assertSame(1, $result['column']);
    }

    #[Test]
    public function to_line_column_handles_crlf(): void
    {
        $source = "name = \"toml\"\r\nversion = 1";
        $span   = new Span(15, 22);
        $result = $span->toLineColumn($source);

        $this->assertSame(2, $result['line']);
        $this->assertSame(1, $result['column']);
    }

    #[Test]
    public function to_string_returns_expected_format(): void
    {
        $this->assertSame('Span(7..13)', (string) new Span(7, 13));
    }

    #[Test]
    public function to_string_works_for_empty_span(): void
    {
        $this->assertSame('Span(0..0)', (string) Span::empty(0));
    }
}
