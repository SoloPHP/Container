# Solo PHP Container

Lightweight PSR-11 dependency injection container with auto-wiring, interface binding, and singleton caching.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/container.svg)](https://packagist.org/packages/solophp/container)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](https://github.com/solophp/container)

## Features

- **PSR-11 Compatible** — Implements `WritableContainerInterface` from `solophp/contracts`
- **Auto-wiring** — Automatic dependency resolution via constructor reflection
- **Interface Binding** — Bind abstracts to concrete implementations
- **Singleton Caching** — Each service resolved once and cached
- **Cache Invalidation** — `set()` invalidates cached instance, `reset()` clears all
- **Service Factories** — Register services as callable factories
- **Circular Dependency Detection** — Cycles in bindings or auto-wiring fail fast with a readable chain

## Installation

```bash
composer require solophp/container
```

## Quick Example

```php
use Solo\Container\Container;

$container = new Container();

// Register a service factory
$container->set(Database::class, fn($c) => new Database('localhost', 'mydb'));

// Bind interface to implementation
$container->bind(LoggerInterface::class, FileLogger::class);

// Resolve with auto-wired dependencies
$repo = $container->get(UserRepository::class);
```

## Usage

### Constructor Registration

```php
$container = new Container([
    'config' => fn() => new Config('config.php'),
    'cache'  => fn($c) => new Cache($c->get('config')),
]);
```

### Auto-wiring

The container automatically resolves class dependencies via constructor reflection:

```php
class UserRepository
{
    public function __construct(
        private Database $database,
        private LoggerInterface $logger
    ) {}
}

// Database and LoggerInterface resolved automatically
$repo = $container->get(UserRepository::class);
```

### Interface Binding

```php
$container->bind(LoggerInterface::class, FileLogger::class);
$container->bind(CacheInterface::class, RedisCache::class);
```

### Re-registering Services

`set()` invalidates the cached instance, so the next `get()` uses the new factory:

```php
$container->set(Connection::class, fn() => new Connection('db1'));
$conn1 = $container->get(Connection::class); // Connection to db1

$container->set(Connection::class, fn() => new Connection('db2'));
$conn2 = $container->get(Connection::class); // Connection to db2
```

### Resetting All Instances

When a root dependency changes and the entire dependency tree needs rebuilding:

```php
$container->reset(); // All cached instances cleared
```

### Circular Dependencies

Cycles are detected and throw `ContainerException` with the full resolution chain:

```php
class A { public function __construct(public B $b) {} }
class B { public function __construct(public A $a) {} }

$container->get(A::class);
// ContainerException: Circular dependency detected: A -> B -> A
```

The same applies to recursive bindings (`bind(A, B); bind(B, A)`) and factories that call `$c->get()` on themselves.

## Error Handling

- `Solo\Container\Exceptions\NotFoundException` — service not found
- `Solo\Container\Exceptions\ContainerException` — service cannot be resolved (non-instantiable, unresolvable parameter, or circular dependency)

## Requirements

- PHP 8.1+

## License

MIT License. See [LICENSE](LICENSE) for details.
