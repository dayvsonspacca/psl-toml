<?php

declare(strict_types=1);

namespace PslToml\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Tree;
use Psl\Type;
use PslToml\Document;

final class DocumentTest extends TestCase
{
    #[Test]
    public function empty_creates_document_with_no_keys(): void
    {
        $doc = Document::empty();

        $this->assertSame([], $doc->keys());
    }

    #[Test]
    public function empty_creates_document_with_empty_array(): void
    {
        $doc = Document::empty();

        $this->assertSame([], $doc->toArray());
    }

    #[Test]
    public function keys_returns_top_level_keys(): void
    {
        $doc = new Document(['name' => 'Alice', 'age' => 30]);

        $this->assertSame(['name', 'age'], $doc->keys());
    }

    #[Test]
    public function keys_does_not_include_nested_keys(): void
    {
        $doc = new Document(['db' => ['host' => 'localhost', 'port' => 5432]]);

        $this->assertSame(['db'], $doc->keys());
    }

    #[Test]
    public function to_array_returns_internal_data(): void
    {
        $data = ['title' => 'TOML', 'version' => 1];
        $doc  = new Document($data);

        $this->assertSame($data, $doc->toArray());
    }

    #[Test]
    public function has_returns_true_for_existing_key(): void
    {
        $doc = new Document(['name' => 'Alice']);

        $this->assertTrue($doc->has('name'));
    }

    #[Test]
    public function has_returns_false_for_absent_key(): void
    {
        $doc = new Document(['name' => 'Alice']);

        $this->assertFalse($doc->has('age'));
    }

    #[Test]
    public function has_returns_true_using_dot_notation(): void
    {
        $doc = new Document(['database' => ['port' => 5432]]);

        $this->assertTrue($doc->has('database.port'));
    }

    #[Test]
    public function has_returns_false_for_absent_dot_notation_key(): void
    {
        $doc = new Document(['database' => ['host' => 'localhost']]);

        $this->assertFalse($doc->has('database.port'));
    }

    #[Test]
    public function has_returns_false_when_intermediate_key_is_not_a_table(): void
    {
        $doc = new Document(['database' => 'not-a-table']);

        $this->assertFalse($doc->has('database.port'));
    }

    #[Test]
    public function get_returns_none_for_absent_key(): void
    {
        $doc = new Document(['name' => 'Alice']);

        $this->assertTrue($doc->get('missing', Type\string())->isNone());
    }

    #[Test]
    public function get_returns_some_with_coerced_value(): void
    {
        $doc = new Document(['name' => 'Alice']);

        $result = $doc->get('name', Type\string());

        $this->assertSame('Alice', $result->unwrap());
    }

    #[Test]
    public function get_returns_some_using_dot_notation(): void
    {
        $doc = new Document(['database' => ['port' => 5432]]);

        $result = $doc->get('database.port', Type\int());

        $this->assertSame(5432, $result->unwrap());
    }

    #[Test]
    public function get_propagates_coercion_exception_for_wrong_type(): void
    {
        $doc = new Document(['age' => 'not-an-int']);

        $this->expectException(\Psl\Type\Exception\CoercionException::class);

        $doc->get('age', Type\int())->unwrap();
    }

    #[Test]
    public function get_returns_none_for_absent_dot_notation_key(): void
    {
        $doc = new Document(['database' => ['host' => 'localhost']]);

        $this->assertTrue($doc->get('database.port', Type\int())->isNone());
    }

    #[Test]
    public function table_returns_none_for_absent_key(): void
    {
        $doc = new Document([]);

        $this->assertTrue($doc->table('missing')->isNone());
    }

    #[Test]
    public function table_returns_none_for_non_table_value(): void
    {
        $doc = new Document(['name' => 'Alice']);

        $this->assertTrue($doc->table('name')->isNone());
    }

    #[Test]
    public function table_returns_document_for_table_value(): void
    {
        $doc = new Document(['database' => ['host' => 'localhost', 'port' => 5432]]);

        $table = $doc->table('database')->unwrap();

        $this->assertInstanceOf(Document::class, $table);
        $this->assertSame(['host', 'port'], $table->keys());
    }

    #[Test]
    public function table_returned_document_preserves_nested_values(): void
    {
        $doc = new Document(['database' => ['host' => 'localhost', 'port' => 5432]]);

        $table = $doc->table('database')->unwrap();

        $this->assertSame('localhost', $table->get('host', Type\string())->unwrap());
        $this->assertSame(5432, $table->get('port', Type\int())->unwrap());
    }

    #[Test]
    public function table_supports_dot_notation(): void
    {
        $doc = new Document(['a' => ['b' => ['c' => 'value']]]);

        $table = $doc->table('a.b')->unwrap();

        $this->assertSame('value', $table->get('c', Type\string())->unwrap());
    }

    #[Test]
    public function to_tree_returns_leaf_for_empty_document(): void
    {
        $doc  = Document::empty();
        $tree = $doc->toTree();

        $this->assertInstanceOf(Tree\LeafNode::class, $tree);
        $this->assertSame(['key' => null, 'value' => []], $tree->getValue());
    }

    #[Test]
    public function to_tree_returns_tree_node_for_document_with_scalar_value(): void
    {
        $doc  = new Document(['name' => 'Alice']);
        $tree = $doc->toTree();

        $this->assertInstanceOf(Tree\TreeNode::class, $tree);

        $children = $tree->getChildren();

        $this->assertCount(1, $children);
        $this->assertInstanceOf(Tree\LeafNode::class, $children[0]);
        $this->assertSame(['key' => 'name', 'value' => 'Alice'], $children[0]->getValue());
    }

    #[Test]
    public function to_tree_returns_nested_tree_for_table(): void
    {
        $doc  = new Document(['database' => ['host' => 'localhost', 'port' => 5432]]);
        $tree = $doc->toTree();

        $this->assertInstanceOf(Tree\TreeNode::class, $tree);

        $children = $tree->getChildren();

        $this->assertCount(1, $children);

        $dbNode = $children[0];

        $this->assertInstanceOf(Tree\TreeNode::class, $dbNode);
        $this->assertSame('database', $dbNode->getValue()['key']);
        $this->assertCount(2, $dbNode->getChildren());
    }

    #[Test]
    public function to_tree_returns_leaf_for_empty_table(): void
    {
        $doc  = new Document(['empty' => []]);
        $tree = $doc->toTree();

        $this->assertInstanceOf(Tree\TreeNode::class, $tree);

        $children = $tree->getChildren();

        $this->assertCount(1, $children);
        $this->assertInstanceOf(Tree\LeafNode::class, $children[0]);
        $this->assertSame(['key' => 'empty', 'value' => []], $children[0]->getValue());
    }

    #[Test]
    public function to_tree_root_value_contains_full_data_array(): void
    {
        $data = ['name' => 'Alice', 'age' => 30];
        $doc  = new Document($data);
        $tree = $doc->toTree();

        $this->assertInstanceOf(Tree\TreeNode::class, $tree);
        $this->assertNull($tree->getValue()['key']);
        $this->assertSame($data, $tree->getValue()['value']);
    }
}
