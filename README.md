# psl-toml

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dayvsonspacca/psl-toml.svg?style=flat-square)](https://packagist.org/packages/dayvsonspacca/psl-toml)
[![Total Downloads](https://img.shields.io/packagist/dt/dayvsonspacca/psl-toml.svg?style=flat-square)](https://packagist.org/packages/dayvsonspacca/psl-toml)
[![License](https://img.shields.io/packagist/l/dayvsonspacca/psl-toml.svg?style=flat-square)](https://packagist.org/packages/dayvsonspacca/psl-toml)
[![PHP Version](https://img.shields.io/packagist/php-v/dayvsonspacca/psl-toml.svg?style=flat-square)](https://packagist.org/packages/dayvsonspacca/psl-toml)
[![Tests](https://img.shields.io/github/actions/workflow/status/dayvsonspacca/psl-toml/tests.yml?style=flat-square&label=tests)](https://github.com/dayvsonspacca/psl-toml/actions/workflows/tests.yml)

A [TOML 1.0](https://toml.io) parser for PHP 8.4+ built on top of [azjezz/psl](https://github.com/azjezz/psl).

## Requirements

- PHP 8.4+
- [`azjezz/psl`](https://github.com/azjezz/psl) ^4.3

## Installation

```bash
composer require dayvsonspacca/psl-toml
```

## Usage

### Parsing a string

```php
use PslToml\Toml;
use Psl\Result\Success;
use Psl\Type;

$result = Toml::parse(<<<TOML
    name    = "Alice"
    version = 1

    [database]
    host = "localhost"
    port = 5432
TOML);

if ($result instanceof Success) {
    $doc = $result->getResult();

    $doc->get('name', Type\string())->unwrap();         // "Alice"
    $doc->get('database.port', Type\int())->unwrap();   // 5432
}
```

### Loading a file

```php
use PslToml\Toml;
use Psl\Result\Success;
use Psl\Type;

$result = Toml::load('/path/to/config.toml');

if ($result instanceof Success) {
    $doc = $result->getResult();
}
```

### Working with the Document

```php
// Check if a key exists
$doc->has('database.host'); // true

// Get a nested table as a Document
$db = $doc->table('database')->unwrap();
$db->get('host', Type\string())->unwrap(); // "localhost"

// List top-level keys
$doc->keys(); // ['name', 'version', 'database']

// Export to a plain PHP array
$doc->toArray();
```

### Result API

Both `Toml::parse()` and `Toml::load()` return a `Psl\Result\ResultInterface<Document>`, so you never need a `try/catch`:

```php
use Psl\Result\Success;
use Psl\Result\Failure;

$result = Toml::parse($source);

if ($result instanceof Failure) {
    $error = $result->getThrowable(); // ParseException with position info
}
```

## License

MIT — see [LICENSE](LICENSE).
