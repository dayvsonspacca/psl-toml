<?php

declare(strict_types=1);

namespace PslToml;

use Psl\File;
use Psl\Result\Failure;
use Psl\Result\ResultInterface;
use Psl\Result\Success;

/**
 * Entry point for parsing TOML documents.
 *
 * Both methods return a {@see ResultInterface} so callers never need a
 * try/catch — inspect the result with {@see ResultInterface::getResult()} or
 * {@see ResultInterface::getThrowable()}.
 *
 * ```php
 * $result = Toml::parse($source);
 * if ($result instanceof \Psl\Result\Success) {
 *     $doc = $result->getResult();
 * }
 *
 * $result = Toml::load('/path/to/config.toml');
 * ```
 *
 * @see Parser
 * @see Document
 */
final class Toml
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Parses a TOML source string and returns the result.
     *
     * An empty string is treated as a valid empty document.
     *
     * @return ResultInterface<Document>
     */
    public static function parse(string $source): ResultInterface
    {
        if ($source === '') {
            return new Success(Document::empty());
        }

        return new Parser($source)->parse();
    }

    /**
     * Reads {@code $path} from disk and parses its contents as TOML.
     *
     * Returns a {@see Failure} if the file cannot be read or the contents
     * are not valid TOML.
     *
     * @return ResultInterface<Document>
     */
    public static function load(string $path): ResultInterface
    {
        try {
            if ($path === '') {
                throw new \InvalidArgumentException('File path cannot be empty.');
            }

            $content = File\read($path);
        } catch (\Throwable $e) {
            return new Failure($e);
        }

        return self::parse($content);
    }
}
