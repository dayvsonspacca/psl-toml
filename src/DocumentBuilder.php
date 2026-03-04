<?php

declare(strict_types=1);

namespace PslToml;

use Psl\Iter;
use Psl\Str;

/**
 * Mutable builder for constructing immutable {@see Document} instances.
 *
 * ```php
 * $doc = (new DocumentBuilder())
 *     ->set('name', 'Alice')
 *     ->set('database.port', 5432)
 *     ->build();
 * ```
 *
 * @see Document
 */
final class DocumentBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Sets {@code $value} at {@code $key}.
     *
     * Dot notation is supported for nested tables, e.g. {@code "database.port"}.
     * Intermediate tables are created automatically if absent.
     *
     * @param mixed $value
     */
    public function set(string $key, mixed $value): self
    {
        $segments = Str\split($key, '.');
        $count = Iter\count($segments);
        $ref = &$this->data;

        foreach ($segments as $i => $segment) {
            if ($i < ($count - 1)) {
                if (!is_array($ref[$segment] ?? null)) {
                    $ref[$segment] = [];
                }

                $ref = &$ref[$segment];
                continue;
            }

            $ref[$segment] = $value;
        }

        return $this;
    }

    /**
     * Builds and returns an immutable {@see Document}.
     */
    public function build(): Document
    {
        return new Document($this->data);
    }
}
