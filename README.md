# Solo Container

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/container.svg)](https://packagist.org/packages/solophp/container)
[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](https://github.com/solophp/container)

Lightweight PSR-11 dependency injection container for PHP 8.4+. Register factories, bind interfaces to implementations and let constructor auto-wiring build the rest: each service is built once and shared. `get(Foo::class)` returns a `Foo` or throws — the container checks it — and PHPStan sees it as a `Foo`, so code analysed at level max needs no casts after it.

## Installation

```bash
composer require solophp/container
```

Requires PHP 8.4+: `lazy()` and `#[Lazy]` build native lazy objects.

## Example

```php
use Solo\Container\Container;

$container = new Container();

// A factory receives the container
$container->set(Database::class, fn($c) => new Database($c->get(Config::class)->dsn));

// An interface resolves to the class bound to it, which must implement it
$container->bind(LoggerInterface::class, FileLogger::class);

// Anything else is built from its constructor
$users = $container->get(UserRepository::class); // a UserRepository, to PHPStan as well
```

`lazy()` and `#[Lazy]` defer construction and break circular dependencies; `#[Inject('id')]` resolves a parameter its type cannot tell apart. Service providers typed against `WritableContainerInterface` from `solophp/contracts` get the same typed `get()`.

Upgrading from 3.x? See [Upgrading to 4.0](https://solophp.github.io/Container/guide/upgrading).

## Documentation

**[Full Documentation](https://solophp.github.io/Container/)**

- [Installation](https://solophp.github.io/Container/guide/installation)
- [Quick Start](https://solophp.github.io/Container/guide/quick-start)
- [Static Analysis](https://solophp.github.io/Container/guide/static-analysis)
- [Upgrading to 4.0](https://solophp.github.io/Container/guide/upgrading)
- [Services & Factories](https://solophp.github.io/Container/features/services)
- [Auto-wiring](https://solophp.github.io/Container/features/autowiring)
- [Interface Binding](https://solophp.github.io/Container/features/binding)
- [Lazy Services](https://solophp.github.io/Container/features/lazy)
- [Injection by Id](https://solophp.github.io/Container/features/inject)
- [Circular Dependencies](https://solophp.github.io/Container/features/circular-dependencies)
- [API Reference](https://solophp.github.io/Container/api/container)

## License

MIT. See [LICENSE](LICENSE).
