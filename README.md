# Solo PHP Container

Lightweight PSR-11 dependency injection container with auto-wiring, interface binding, and singleton caching.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/container.svg)](https://packagist.org/packages/solophp/container)
[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](https://github.com/solophp/container)

## Features

- **PSR-11 Compatible** — Implements `WritableContainerInterface` from `solophp/contracts`
- **Auto-wiring** — Automatic dependency resolution via constructor reflection
- **Interface Binding** — Bind abstracts to concrete implementations
- **Singleton Caching** — Each service resolved once and cached
- **Cache Invalidation** — `set()` invalidates cached instance, `reset()` clears all
- **Service Factories** — Register services as callable factories
- **Lazy Resolution** — `lazy()` (by id) and `#[Lazy]` (by injection point) defer construction via native proxies and break cycles
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

### Lazy Services — `lazy()`

`lazy()` marks an id so the container resolves it to a **native lazy proxy** instead of
the real instance. The proxy is returned immediately and constructs the real object only
on first use:

```php
$container->lazy(ReportBuilder::class);

$proxy = $container->get(ReportBuilder::class); // not constructed yet
$proxy->generate();                             // constructed on first access
```

The proxy is a real instance of the class — it satisfies type hints and is cached as a
singleton. The id may be bound: the proxy is built from the bound concrete class, so it
still satisfies the abstract type:

```php
$container->bind(LoggerInterface::class, FileLogger::class);
$container->lazy(LoggerInterface::class); // get(LoggerInterface::class) yields a proxy
```

The id must resolve (directly or through a binding) to an instantiable class. An absent id
throws `NotFoundException`; an id that resolves to a non-class (e.g. a plain factory
service) throws `ContainerException`. Marking an id lazy also invalidates any instance
already cached for it, mirroring `set()`.

### Lazy Injection Points — `#[Lazy]`

To make a single constructor dependency lazy without changing how the service resolves
elsewhere, put `#[Lazy]` on the parameter. The proxy resolves to the same shared instance
`get()` would return:

```php
use Solo\Container\Attribute\Lazy;

class Mailer { public function __construct(#[Lazy] private Templates $templates) {} }
```

`#[Lazy]` and `lazy()` are complementary: `lazy($id)` is container-level (one decision, no
change to consumer classes); `#[Lazy]` is per-injection-point (precise, self-documenting).
A `#[Lazy]` interface parameter is proxied through its binding to the concrete class.

> The injected value is a proxy, so `$obj->dep !== $container->get(Dep::class)` by identity
> even though both forward to the same instance. A lazy id whose factory yields a
> non-object surfaces the type error on first access, not at `get()`.

### Circular Dependencies

By default cycles are detected and throw `ContainerException` with the full resolution chain:

```php
class A { public function __construct(public B $b) {} }
class B { public function __construct(public A $a) {} }

$container->get(A::class);
// ContainerException: Circular dependency detected: A -> B -> A
```

The same applies to recursive bindings (`bind(A, B); bind(B, A)`) and factories that call `$c->get()` on themselves.

To break a genuine cycle, make one participant lazy — container-level with `lazy()`, or at
the exact injection point with `#[Lazy]`. The lazy side is injected as a proxy, so the
other constructor completes without re-entering resolution:

```php
$container->lazy(B::class);                                          // B is always a proxy
// — or, only this edge —
class A { public function __construct(#[Lazy] public B $b) {} }

$a = $container->get(A::class); // resolves; $a->b is a lazy proxy, and $a->b->a === $a
```

## Error Handling

- `Solo\Container\Exceptions\NotFoundException` — service not found
- `Solo\Container\Exceptions\ContainerException` — service cannot be resolved (non-instantiable, unresolvable parameter, or circular dependency)

## Requirements

- PHP 8.4+ (uses native lazy objects for `lazy()` and `#[Lazy]`)

## License

MIT License. See [LICENSE](LICENSE) for details.
