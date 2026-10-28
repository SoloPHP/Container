# Exceptions

Both exceptions implement the PSR-11 interfaces, so code that depends only on `psr/container` can catch them:

| Exception | PSR-11 interface | Meaning |
|---|---|---|
| `Solo\Container\Exceptions\NotFoundException` | `NotFoundExceptionInterface` | the id passed to `get()` is unknown |
| `Solo\Container\Exceptions\ContainerException` | `ContainerExceptionInterface` | the id is known, but its entry cannot be built |

Both extend `\Exception`, and neither extends the other. PSR-11's `NotFoundExceptionInterface` extends `ContainerExceptionInterface`, so catch the interface to handle both:

```php
use Psr\Container\ContainerExceptionInterface;

try {
    $service = $container->get($id);
} catch (ContainerExceptionInterface $e) {
    // NotFoundException or ContainerException
}
```

An exception thrown by a factory reaches the caller of `get()` as it is, with one exception: a PSR-11 `NotFoundExceptionInterface` — the factory asked a container for an id it does not have — becomes a `ContainerException`, as below.

## NotFoundException

```text
Service 'mailer' not found in container.
```

Thrown by `get()` when the requested id has no factory and no binding and names no existing class — including an interface that is neither bound nor registered, and an id marked `lazy()` that resolves to nothing.

A missing *dependency* is not a missing entry: while an entry is built, a `NotFoundException` becomes a `ContainerException` that names the chain and keeps it as its previous exception, as PSR-11 requires.

## ContainerException

Thrown when an entry is known but cannot be built:

| Message | Cause |
|---|---|
| `Service 'X' is Y, not an instance of X.` | a factory or binding under a class or interface name that yields something else |
| `Cannot resolve A -> B: Service 'C' not found in container.` | a dependency the container does not know, needed while building `A` and then `B` |
| `Class 'X' is not instantiable.` | an abstract class, an enum, a class with a non-public constructor |
| `Cannot resolve parameter '$x' in class 'Y'.` | a parameter with no class type, no default and no `#[Inject]` |
| `Circular dependency detected: A -> B -> A` | a cycle in auto-wiring, bindings or factories |
| `Cannot create a lazy proxy for 'X': …` | a lazy id or `#[Lazy]` parameter that does not resolve to a class PHP can proxy |
| `Cannot initialize the lazy proxy for 'X': it resolved to Y, not an instance of exactly C.` | on first use, a lazy id or `#[Lazy]` parameter whose entry is not an instance of exactly the proxied class `C` |
| `Service 'id' injected into '$x' in class 'Y' is Z, not T.` | an `#[Inject]` value, or the proxy of a `#[Lazy]` parameter, that does not match the parameter type |
| `Invalid #[Inject] on '$x' in class 'Y': …` | an `#[Inject]` without an id, or repeated |
