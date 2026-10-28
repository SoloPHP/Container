# Attributes

Both attributes go on constructor parameters of auto-wired classes.

## Inject

```php
namespace Solo\Container\Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(public string $id)
    {
    }
}
```

Resolves the parameter from the service id `$id` instead of its type.

- The parameter receives what `get($id)` returns. An id that is not registered leaves the parameter at its default value; without one, building the class throws `ContainerException`.
- A class or interface type is checked against the value: another type, or `null` for a non-nullable parameter, throws `ContainerException`. Other types take the value as it is.
- Combined with `#[Lazy]`, the parameter receives a proxy that resolves the id on first use; the id must then yield an instance of exactly the class the type resolves to.
- Without an id, or repeated, it throws `ContainerException`.

See [Injection by Id](/features/inject).

## Lazy

```php
namespace Solo\Container\Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Lazy
{
}
```

Injects a lazy proxy of the shared service instead of resolving it eagerly. The proxy is built from the class the parameter type resolves to, through its binding for an interface; a class without properties is injected as a real instance, as PHP cannot defer it. Ignored on a parameter without a class or interface type.

See [Lazy Services](/features/lazy).
