# PslToml — Claude Context

## Architecture

```
source → Lexer → Parser → Document
                               ↑
                        DocumentBuilder
```

## Commands

```bash
composer test    # run tests
composer check   # fmt:check + lint + analyze + test
```

## Code conventions

- Always use curly braces for control structures, even for single-line bodies
- Always use explicit return type declarations on all methods and functions
- Use PHP 8 constructor property promotion in `__construct()`
- Prefer PHPDoc blocks over inline comments; never use inline comments unless logic is exceptionally complex
- Use descriptive names — e.g. `isValidKey`, not `check()`
- Check sibling files for conventions before creating new ones
- Docblocks with `@param`, `@return`, `@throws` on all public methods (required by Mago)
- Use PSL utilities (`Psl\Str`, `Psl\Vec`, `Psl\Iter`, etc.) consistently

## Test coverage

- 100% code coverage is required. Always run `composer test:coverage` after writing tests to verify.
- Use `@codeCoverageIgnore` only on guards that are provably unreachable (e.g. null checks on values the type system guarantees are non-null).

## Test conventions

- `#[Test]` attribute (not annotation)
- `snake_case` method names describing behavior
- No Reflection for private methods
- One behavior per test

## Commit conventions

Conventional commits, split by responsibility:

| Prefix | Usage |
|---|---|
| `feat(<scope>):` | new source file |
| `test(<scope>):` | new test file |
| `chore:` | config, tooling, non-logic adjustments |

- Scope reflects the subsystem when applicable (`lexer`, `exception`, `parser`)
- Root-namespace classes carry no scope (e.g. `feat: add Document value object`)
- Always commit `feat` before its corresponding `test`

## Design decisions

- `Document` is immutable; `DocumentBuilder` is mutable and produces `Document` via `->build()`
- Missing key → `Option\None`; value with wrong type → propagates `Type\*` exception
- `Toml::load()` and `Toml::parse()` return `Psl\Result`
- `\DateTimeImmutable` for all temporal types, including `local-time`
- The Emitter normalizes output (e.g. `0xFF` becomes `255`) — original formatting is not preserved

## Roadmap

1. `Parser` — consumes tokens from `Lexer`, produces `Document`, throws `ParseException`, public entry point returns `Psl\Result`
2. `Parser` tests
3. `Toml` facade — `Toml::load(string $path)` and `Toml::parse(string $source)` returning `Result<Document, ParseException>`
