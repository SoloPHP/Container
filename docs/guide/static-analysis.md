# Static Analysis

`get()` is typed for PHPStan, and the container guarantees the type at runtime: an id that names a class or interface resolves to an instance of it, or `get()` throws.

```php
$users = $container->get(UserRepository::class);   // UserRepository
$logger = $container->get(LoggerInterface::class); // LoggerInterface
$name = $container->get('app.name');               // mixed
```

At level 9 or max, PHPStan allows nothing on `mixed` but passing it on, so a `get()` typed `mixed` needs a cast or an assertion before each use. A typed `get()` needs none, and there is nothing to configure.

## How Ids Are Typed

| Argument of `get()` | Type |
|---|---|
| `Foo::class`, or a string naming an existing class or interface | `Foo` |
| a `class-string<Foo>` | `Foo` |
| a `class-string` | `object` |
| any other string, such as `'app.name'` or `$id` | `mixed` |

This comes from the PHPDoc of `Container::get()`:

```php
/**
 * @template T of object
 * @param class-string<T>|string $id
 * @return ($id is class-string<T> ? T : mixed)
 */
public function get(string $id): mixed
```

`Solo\Contracts\Container\WritableContainerInterface` declares `get()` the same way, so code typed against the interface is covered as well.

## Why the Type Holds

The container checks what it returns under a class name:

- a factory registered under a class or interface name must return an instance of it — not another class, not `null`;
- a binding must lead to a class that implements or extends the abstract;
- auto-wired classes and lazy proxies are instances of the class by construction.

Anything else throws `ContainerException` when the id is resolved, so a value PHPStan types as `Foo` is a `Foo`. See [Services & Factories](/features/services#ids-and-values).

## Service Providers

Providers receive the container as `WritableContainerInterface`. Its `set()` takes factories as `callable(static): mixed`, so PHPStan infers an untyped factory parameter as the container, and each `get()` as its class:

```php
use Solo\Contracts\Container\WritableContainerInterface;

final class DatabaseServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(Migrator::class, fn($c) => new Migrator(
            $c->get(Connection::class),   // Connection
            $c->get(Config::class)->path, // Config
        ));
    }
}
```

On a `Container`, an untyped factory parameter is inferred as `Container`. A factory that cannot take the container — one with a required second parameter, or a first parameter of another type — is reported.

## PSR-11

`Psr\Container\ContainerInterface` promises nothing about the class of an entry, so `get()` on it stays `mixed`. Type factory parameters as `WritableContainerInterface`, or leave them untyped, rather than as the PSR interface:

```php
fn(ContainerInterface $c) => new Migrator($c->get(Connection::class));          // mixed — reported at level max
fn(WritableContainerInterface $c) => new Migrator($c->get(Connection::class));  // Connection
fn($c) => new Migrator($c->get(Connection::class));                             // Connection
```

Libraries that only read from a container keep depending on PSR-11.

## Upgrading from 3.x

Checks that were needed while `get()` returned `mixed` may now be reported as always true:

```php
$logger = $container->get(LoggerInterface::class);
assert($logger instanceof LoggerInterface); // always true — remove it
```

See [Upgrading to 4.0](/guide/upgrading).
