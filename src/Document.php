<?php

declare(strict_types=1);

namespace PslToml;

use Psl\Iter;
use Psl\Option;
use Psl\Str;
use Psl\Type;
use Psl\Type\TypeInterface;
use Psl\Vec;

/**
 * An immutable representation of a parsed TOML document.
 *
 * Values are stored as native PHP types and validated on access via
 * {@see self::get()} using a {@see TypeInterface} coercion. If a key is
 * absent the method returns {@see Option\none()}; if the value exists but
 * does not satisfy the given type, the type's exception is propagated.
 *
 * Keys support dot notation to traverse nested tables:
 *
 * ```php
 * $doc->get('database.port', Type\int());
 * ```
 *
 * @see DocumentBuilder
 */
final class Document
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
    ) {}

    /**
     * Returns an empty document.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Returns {@see Option\Some} containing the value at {@code $key} coerced
     * to {@code $type}, or {@see Option\None} if the key does not exist.
     *
     * Dot notation is supported for nested tables, e.g. {@code "database.port"}.
     *
     * @template T
     *
     * @param  TypeInterface<T>  $type
     * @return Option\Option<T>
     *
     * @throws \Psl\Type\Exception\CoercionException if the value exists but cannot be coerced to {@code $type}.
     * @throws \Psl\Type\Exception\AssertException   if the value exists but does not satisfy {@code $type}.
     */
    public function get(string $key, TypeInterface $type): Option\Option
    {
        /** @var mixed $value */
        $value = $this->resolve($key);

        if ($value === null) {
            return Option\none();
        }

        return Option\some($type->coerce($value));
    }

    /**
     * Returns {@code true} if {@code $key} exists in the document.
     *
     * Dot notation is supported for nested tables.
     */
    public function has(string $key): bool
    {
        return $this->resolve($key) !== null;
    }

    /**
     * Returns {@see Option\Some} containing the nested {@see Document} at
     * {@code $key}, or {@see Option\None} if the key does not exist or is
     * not a table.
     *
     * @throws \Psl\Type\Exception\CoercionException
     */
    public function table(string $key): Option\Option
    {
        /** @var mixed $value */
        $value = $this->resolve($key);

        if (!is_array($value)) {
            return Option\none();
        }

        return Option\some(new self(Type\dict(Type\string(), Type\mixed())->coerce($value)));
    }

    /**
     * Returns all top-level keys in the document.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return Vec\keys($this->data);
    }

    /**
     * Returns a plain PHP array representation of the document.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Resolves a dot-notation key to its value, or {@code null} if absent.
     */
    private function resolve(string $key): mixed
    {
        $segments = Str\split($key, '.');
        /** @var mixed $current */
        $current = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !Iter\contains_key($current, $segment)) {
                return null;
            }

            /** @var mixed $current */
            $current = $current[$segment];
        }

        return $current;
    }
}
