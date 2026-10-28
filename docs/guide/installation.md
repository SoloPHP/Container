# Installation

## Requirements

- PHP 8.4 or higher — `lazy()` and `#[Lazy]` build native lazy objects, new in PHP 8.4
- `solophp/contracts` 1.7 or higher, which brings `psr/container`; Composer installs both

## Composer

Install the package via Composer:

```bash
composer require solophp/container
```

## Dependencies

```json
{
    "require": {
        "php": ">=8.4",
        "solophp/contracts": "^1.7"
    }
}
```

## Basic Setup

```php
use Solo\Container\Container;

$container = new Container();
```

The container needs no configuration: services are registered with `set()` and `bind()`, and any other class is [auto-wired](/features/autowiring).

## Static Analysis

`get()` is typed for PHPStan with no configuration, and the container guarantees the type at runtime: `get(Foo::class)` returns a `Foo` or throws. See [Static Analysis](/guide/static-analysis).

## Versioning

Solo Container follows semantic versioning. Its public API is the `Container` class, the `#[Inject]` and `#[Lazy]` attributes and the exceptions. Parameter names are not part of it: `Container` is marked `@no-named-arguments`, so call its methods with positional arguments. Classes in `Solo\Container\Internal` are marked `@internal` — implementation details that may change in any release.

## Next Steps

- [Quick Start](/guide/quick-start) — Register and resolve your first services
- [Static Analysis](/guide/static-analysis) — `get()` typed with PHPStan
- [Upgrading to 4.0](/guide/upgrading) — What changed since 3.x
