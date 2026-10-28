# Upgrading to 4.0

4.0 makes the container strict: an id that names a class resolves to an instance of that class or fails, each id has one registration, and a missing dependency is reported as an error building the service that needs it. The sections below list everything that behaves differently from 3.x.

## Requirements

- `solophp/contracts` 1.7 or later. Its `WritableContainerInterface` types `get()` and the factories of `set()`; Composer updates it with the container, and Contracts 1.7 cannot be installed together with Container 3.x.
- PHP 8.4, as before.

## Class Names Resolve to Instances

**3.x:** a factory or a binding registered under a class or interface name could yield anything, and `get()` returned it unchecked.

**4.0:** the entry must be an instance of that class or interface. Anything else — another class, `null`, a string — throws `ContainerException`:

```php
$container->set(LoggerInterface::class, fn() => null);

$container->get(LoggerInterface::class);
// 3.x: null
// 4.0: ContainerException: Service 'LoggerInterface' is null, not an instance of LoggerInterface.
```

The same applies to `bind()`: binding an interface to a class that does not implement it throws when the id is resolved.

**To upgrade:** register values of another shape under an id of your own, such as `'logger.optional'`, and bind abstracts only to classes that implement or extend them. An id that is not a class name, such as `'app.name'`, is not checked.

## One Registration per Id

**3.x:** an id could have both a factory and a binding, and the factory won whatever the order. `bind()` kept an instance already cached under the id.

**4.0:** the last `set()` or `bind()` of an id replaces the previous one and drops the instance cached for it:

```php
$container->set('logger', fn() => new NullLogger());
$container->bind('logger', FileLogger::class);

$container->get('logger');
// 3.x: NullLogger — the factory won
// 4.0: FileLogger — the binding replaced the factory
```

## Missing Dependencies

**3.x:** a dependency missing anywhere in the graph threw `NotFoundException`, as if the id you asked for were unknown.

**4.0:** `NotFoundException` means only that the id passed to `get()` is unknown, as PSR-11 requires. A dependency that is missing while an entry is built throws `ContainerException`, which names the chain and keeps the `NotFoundException` as its previous exception:

```php
$container->get(ReportService::class);
// 3.x: NotFoundException: Service 'Mailer' not found in container.
// 4.0: ContainerException: Cannot resolve ReportService -> Notifier: Service 'Mailer' not found in container.
```

**To upgrade:** code that catches `NotFoundExceptionInterface` to detect a broken service should catch `ContainerExceptionInterface`, which covers both.

## Optional Dependencies

**3.x:** a class or interface parameter was always fetched from the container, even when it had a default value; an unknown type threw.

**4.0:** a dependency the container does not know leaves the parameter at its default value:

```php
final class Checkout
{
    public function __construct(private ?PaymentGateway $gateway = null)
    {
    }
}

$container->get(Checkout::class);
// 3.x: NotFoundException: Service 'PaymentGateway' not found in container.
// 4.0: a Checkout with $gateway === null
```

The same holds for an `#[Inject]` id that is not registered. A dependency the container knows but cannot build still throws — only an unknown one falls back.

## Lazy Services

- A lazy id whose factory yields anything but an instance of exactly the proxied class throws `ContainerException` on first use, where 3.x surfaced a PHP `TypeError`.
- A lazy id bound to a class that is not an instance of the id throws `ContainerException` at `get()`.
- An `#[Inject]` id with `#[Lazy]` that is not registered fails when the dependent class is built, not when the proxy is first used.
- A class without properties is built at once. PHP cannot defer it: 3.x returned a "proxy" whose factory and constructor never ran.

## Constructor

`new Container([...])` registers each factory through `set()`, so a value that is not callable throws `TypeError` at construction instead of failing at the first `get()`.

## Static Analysis

- `Container::get()` and `WritableContainerInterface::get()` are typed with no configuration: `get(Foo::class)` is a `Foo`, any other id is `mixed`. The container guarantees it at runtime.
- An untyped factory parameter is inferred as the container. If your providers type factories against PSR-11 — `fn(ContainerInterface $c)` — drop the type or use `WritableContainerInterface`: PSR-11 makes no promise about the class of an entry, so its `get()` stays `mixed`.
- Checks written while `get()` returned `mixed` — `assert($x instanceof Foo)`, `assertNotNull()` — may now be reported as always true. Remove them.

See [Static Analysis](/guide/static-analysis).
