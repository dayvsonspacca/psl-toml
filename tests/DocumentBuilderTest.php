<?php

declare(strict_types=1);

namespace PslToml\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type;
use PslToml\Document;
use PslToml\DocumentBuilder;

final class DocumentBuilderTest extends TestCase
{
    #[Test]
    public function build_returns_empty_document_when_no_values_set(): void
    {
        $doc = (new DocumentBuilder())->build();

        $this->assertSame([], $doc->keys());
        $this->assertSame([], $doc->toArray());
    }

    #[Test]
    public function build_returns_document_instance(): void
    {
        $doc = (new DocumentBuilder())->build();

        $this->assertInstanceOf(Document::class, $doc);
    }

    #[Test]
    public function set_stores_string_value(): void
    {
        $doc = (new DocumentBuilder())
            ->set('name', 'Alice')
            ->build();

        $this->assertSame('Alice', $doc->get('name', Type\string())->unwrap());
    }

    #[Test]
    public function set_stores_integer_value(): void
    {
        $doc = (new DocumentBuilder())
            ->set('age', 30)
            ->build();

        $this->assertSame(30, $doc->get('age', Type\int())->unwrap());
    }

    #[Test]
    public function set_stores_float_value(): void
    {
        $doc = (new DocumentBuilder())
            ->set('pi', 3.14)
            ->build();

        $this->assertSame(3.14, $doc->get('pi', Type\float())->unwrap());
    }

    #[Test]
    public function set_stores_bool_value(): void
    {
        $doc = (new DocumentBuilder())
            ->set('enabled', true)
            ->build();

        $this->assertTrue($doc->get('enabled', Type\bool())->unwrap());
    }

    #[Test]
    public function set_stores_array_value(): void
    {
        $doc = (new DocumentBuilder())
            ->set('ports', [8080, 8443])
            ->build();

        $this->assertSame([8080, 8443], $doc->get('ports', Type\vec(Type\int()))->unwrap());
    }

    #[Test]
    public function set_overwrites_existing_value(): void
    {
        $doc = (new DocumentBuilder())
            ->set('name', 'Alice')
            ->set('name', 'Bob')
            ->build();

        $this->assertSame('Bob', $doc->get('name', Type\string())->unwrap());
    }

    #[Test]
    public function set_is_fluent(): void
    {
        $builder = new DocumentBuilder();

        $this->assertSame($builder, $builder->set('name', 'Alice'));
    }

    #[Test]
    public function set_with_dot_notation_creates_nested_table(): void
    {
        $doc = (new DocumentBuilder())
            ->set('database.port', 5432)
            ->build();

        $this->assertSame(5432, $doc->get('database.port', Type\int())->unwrap());
    }

    #[Test]
    public function set_with_dot_notation_creates_intermediate_tables_automatically(): void
    {
        $doc = (new DocumentBuilder())
            ->set('a.b.c', 'deep')
            ->build();

        $this->assertTrue($doc->has('a'));
        $this->assertTrue($doc->has('a.b'));
        $this->assertSame('deep', $doc->get('a.b.c', Type\string())->unwrap());
    }

    #[Test]
    public function set_with_dot_notation_merges_into_existing_table(): void
    {
        $doc = (new DocumentBuilder())
            ->set('database.host', 'localhost')
            ->set('database.port', 5432)
            ->build();

        $this->assertSame('localhost', $doc->get('database.host', Type\string())->unwrap());
        $this->assertSame(5432, $doc->get('database.port', Type\int())->unwrap());
    }

    #[Test]
    public function set_after_build_does_not_modify_built_document(): void
    {
        $builder = new DocumentBuilder();
        $builder->set('name', 'Alice');

        $doc = $builder->build();

        $builder->set('name', 'Bob');

        $this->assertSame('Alice', $doc->get('name', Type\string())->unwrap());
    }

    #[Test]
    public function build_called_twice_returns_independent_documents(): void
    {
        $builder = new DocumentBuilder();
        $builder->set('name', 'Alice');

        $first  = $builder->build();
        $builder->set('name', 'Bob');
        $second = $builder->build();

        $this->assertSame('Alice', $first->get('name', Type\string())->unwrap());
        $this->assertSame('Bob', $second->get('name', Type\string())->unwrap());
    }
}
