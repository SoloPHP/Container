# Lazy Services

A lazy service is resolved to a native PHP 8.4 lazy proxy: a real instance of the class that builds the actual object the first time its state is used. Use it to defer an expensive service, or to break a [circular dependency](/features/circular-dependencies).

## Lazy Ids — `lazy()`

`lazy()` marks an id so that it always resolves to a proxy:

```php
$container->lazy(ReportBuilder::class);

$proxy = $container->get(ReportBuilder::class); // not constructed yet
$proxy->generate();                             // constructed on first use
```

The proxy is cached like any instance, so `get()` and every injection of the id receive the same proxy. It is an instance of the class, so it satisfies type hints.

The id may be bound: the proxy is then built from the bound class, which must implement or extend the id:

```php
$container->bind(LoggerInterface::class, FileLogger::class);
$container->lazy(LoggerInterface::class); // get(LoggerInterface::class) yields a FileLogger proxy
```

The id may also have a factory: the proxy runs it on first use. The factory must return an instance of exactly the proxied class — PHP cannot forward a proxy to a subclass — or first use throws `ContainerException`.

The id must resolve, directly or through a binding, to an instantiable class:

- an unknown id throws `NotFoundException`;
- an id that resolves to no class — a factory under a name such as `'mailer'` — throws `ContainerException`;
- PHP cannot proxy some internal classes, such as `ArrayObject`: `ContainerException`.

Marking an id lazy drops the instance already cached for it, as `set()` does.

## Lazy Injection Points — `#[Lazy]`

To make one constructor dependency lazy without changing how the service resolves elsewhere, put `#[Lazy]` on the parameter:

```php
use Solo\Container\Attribute\Lazy;

final class Mailer
{
    public function __construct(#[Lazy] private Templates $templates)
    {
    }
}
```

The parameter receives a proxy that resolves to the same shared instance `get(Templates::class)` returns. An interface parameter is proxied through its binding to the concrete class. On a parameter without a class or interface type, `#[Lazy]` is ignored.

`lazy()` and `#[Lazy]` are complementary: `lazy($id)` is one decision in the container, with no change to the classes that use the service; `#[Lazy]` marks a single injection point, precise and visible in the class.

::: warning Identity
An injected `#[Lazy]` value is a proxy of its own: `$mailer->templates !== $container->get(Templates::class)` by identity, although both forward to the same instance.
:::

## When the Object Is Built

PHP builds the real object when the proxy's state is first used: a property read or write, including one inside a method of the class. Code that only passes the proxy around, or calls methods that touch no property, does not build it.

A class without properties has no state for PHP to defer, so the container builds it at once instead of returning a proxy: its factory or constructor always runs.

## With `#[Inject]`

`#[Lazy]` combines with [`#[Inject]`](/features/inject#lazy-injection): the parameter receives a proxy that resolves the id on first use.
