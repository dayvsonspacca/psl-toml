<?php

declare(strict_types=1);

namespace PslToml\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Result\Failure;
use Psl\Result\Success;
use Psl\Type;
use PslToml\Document;
use PslToml\Toml;

final class TomlTest extends TestCase
{
    // -------------------------------------------------------------------------
    // parse()
    // -------------------------------------------------------------------------

    #[Test]
    public function parse_returns_success_for_valid_source(): void
    {
        $result = Toml::parse('name = "Alice"');

        $this->assertInstanceOf(Success::class, $result);
    }

    #[Test]
    public function parse_returns_document_with_correct_values(): void
    {
        $doc = Toml::parse('name = "Alice"')->getResult();

        $this->assertSame('Alice', $doc->get('name', Type\string())->unwrap());
    }

    #[Test]
    public function parse_returns_empty_document_for_empty_string(): void
    {
        $result = Toml::parse('');

        $this->assertInstanceOf(Success::class, $result);
        $this->assertInstanceOf(Document::class, $result->getResult());
        $this->assertSame([], $result->getResult()->keys());
    }

    #[Test]
    public function parse_returns_failure_for_invalid_source(): void
    {
        $result = Toml::parse('name = $invalid');

        $this->assertInstanceOf(Failure::class, $result);
    }

    // -------------------------------------------------------------------------
    // load()
    // -------------------------------------------------------------------------

    #[Test]
    public function load_returns_success_for_valid_file(): void
    {
        $path   = tempnam(sys_get_temp_dir(), 'toml_');
        $result = false;

        try {
            file_put_contents($path, 'name = "Alice"');
            $result = Toml::load($path);
        } finally {
            unlink($path);
        }

        $this->assertInstanceOf(Success::class, $result);
    }

    #[Test]
    public function load_returns_document_with_correct_values(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'toml_');
        $doc  = null;

        try {
            file_put_contents($path, 'name = "Alice"');
            $doc = Toml::load($path)->getResult();
        } finally {
            unlink($path);
        }

        $this->assertSame('Alice', $doc->get('name', Type\string())->unwrap());
    }

    #[Test]
    public function load_returns_empty_document_for_empty_file(): void
    {
        $path   = tempnam(sys_get_temp_dir(), 'toml_');
        $result = false;

        try {
            file_put_contents($path, '');
            $result = Toml::load($path);
        } finally {
            unlink($path);
        }

        $this->assertInstanceOf(Success::class, $result);
        $this->assertSame([], $result->getResult()->keys());
    }

    #[Test]
    public function load_returns_failure_for_non_existent_file(): void
    {
        $result = Toml::load('/non/existent/file.toml');

        $this->assertInstanceOf(Failure::class, $result);
    }

    #[Test]
    public function load_returns_failure_for_empty_path(): void
    {
        $result = Toml::load('');

        $this->assertInstanceOf(Failure::class, $result);
    }

    #[Test]
    public function load_returns_failure_for_invalid_toml_file(): void
    {
        $path   = tempnam(sys_get_temp_dir(), 'toml_');
        $result = false;

        try {
            file_put_contents($path, 'name = $invalid');
            $result = Toml::load($path);
        } finally {
            unlink($path);
        }

        $this->assertInstanceOf(Failure::class, $result);
    }
}
