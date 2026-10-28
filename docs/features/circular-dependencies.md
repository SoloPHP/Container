# Circular Dependencies

## Detection

A cycle fails at once with `ContainerException` and the whole resolution chain:

```php
final class A { public function __construct(public B $b) {} }
final class B { public function __construct(public A $a) {} }

$container->get(A::class);
// ContainerException: Circular dependency detected: A -> B -> A
```

The same applies to recursive bindings (`bind(A::class, B::class)` with `bind(B::class, A::class)`) and to a factory that calls `$c->get()` on its own id.

## Breaking a Cycle

To break a genuine cycle, make one participant lazy — in the container with `lazy()`, or at the exact injection point with `#[Lazy]`. The lazy side is injected as a proxy, so the other constructor completes without resolving it again:

```php
$container->lazy(B::class);                                     // B is always a proxy
// — or, only this edge —
final class A { public function __construct(#[Lazy] public B $b) {} }

$a = $container->get(A::class); // resolves; $a->b is a lazy proxy, and $a->b->a === $a
```

When the proxy is first used, it builds `B`, which receives the `A` instance already cached. See [Lazy Services](/features/lazy) for what a proxy can and cannot do.
