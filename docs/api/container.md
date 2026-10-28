# Container

```php
namespace Solo\Container;

final class Container implements WritableContainerInterface
```

`Solo\Contracts\Container\WritableContainerInterface` extends `Psr\Container\ContainerInterface` with `set()`, and types `get()` the same way as below. The other methods are specific to this container.

---

## __construct()

```php
/** @param array<string, callable(Container): mixed> $services */
public function __construct(array $services = [])
```

Registers each factory with `set()`; a value that is not callable throws `TypeError`.

## set()

```php
/** @param callable(Container): mixed $factory */
public function set(string $id, callable $factory): void
```

Registers the factory of an id. It replaces the id's factory or binding and drops the instance cached for it. The factory runs on the first `get()` of the id and receives the container. Under a class or interface name, it must return an instance of that class. See [Services & Factories](/features/services).

## bind()

```php
/** @param class-string $concrete */
public function bind(string $abstract, string $concrete): void
```

Makes `$abstract` resolve to `$concrete`, which is resolved like any other id; both ids then share one instance. It replaces the factory or binding of `$abstract` and drops the instance cached for it. When `$abstract` names a class or interface, `$concrete` must implement or extend it. See [Interface Binding](/features/binding).

## lazy()

```php
public function lazy(string $id): void
```

Marks an id so that it always resolves to a lazy proxy of the class it maps to, directly or through a binding. Drops the instance already cached for the id. See [Lazy Services](/features/lazy).

## has()

```php
public function has(string $id): bool
```

`true` when the id has a factory or a binding, or names an existing class. A `true` result does not guarantee that `get()` succeeds.

## get()

```php
/**
 * @template T of object
 * @param class-string<T>|string $id
 * @return ($id is class-string<T> ? T : mixed)
 * @throws NotFoundException
 * @throws ContainerException
 */
public function get(string $id): mixed
```

Returns the entry of an id, building it on first use: a cached instance, else a lazy proxy for an id marked `lazy()`, else the result of its factory, of its binding, or of auto-wiring its class.

- An id that names a class or interface resolves to an instance of it; any other result throws `ContainerException`.
- `NotFoundException` means the id itself is unknown. Any other failure — a dependency that is missing or cannot be built — is a `ContainerException`.

See [Static Analysis](/guide/static-analysis) for how the return type is inferred.

## reset()

```php
public function reset(): void
```

Drops every cached instance. Factories, bindings and lazy marks stay.
