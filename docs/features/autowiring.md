# Auto-wiring

An id with no factory and no binding that names an existing class is built from the class constructor, and cached under the class name like any other service:

```php
final class ReportService
{
    public function __construct(
        private Database $database,      // get(Database::class)
        private LoggerInterface $logger, // get(LoggerInterface::class) — its binding
        private int $pageSize = 50,      // the default
    ) {
    }
}

$reports = $container->get(ReportService::class);
```

## Resolving Parameters

Each constructor parameter is resolved in turn:

1. With `#[Inject('id')]`, from that service id — see [Injection by Id](/features/inject).
2. A class or interface type is fetched from the container: its factory, its binding, or auto-wiring again. With `#[Lazy]`, a lazy proxy is injected instead — see [Lazy Services](/features/lazy).
3. Any other parameter takes its default value.
4. Without one, the class cannot be built: `ContainerException`.

A class without a constructor is created with `new`. A parameter typed `self` or `parent` resolves to the declaring class or its parent.

## The Container Itself

The container resolves to itself under its own class and the two interfaces it implements, with nothing to register:

```php
use Psr\Container\ContainerInterface;
use Solo\Container\Container;
use Solo\Contracts\Container\WritableContainerInterface;

$container->get(Container::class) === $container;                  // true
$container->get(ContainerInterface::class) === $container;         // true
$container->get(WritableContainerInterface::class) === $container; // true
```

So a class that needs the container — a factory of handlers, a validation rule that looks services up by name — declares it like any other dependency and receives the container that builds it:

```php
final class HandlerFactory
{
    public function __construct(private ContainerInterface $container)
    {
    }
}
```

`has()` is `true` for the three ids, and `reset()` does not affect them: the container is not one of its cached instances.

A registration takes over. After `set()` or `bind()` on one of these ids, that id resolves to what was registered, by the same rules as any other id; the other two still resolve to the container. `#[Inject('id')]` on a parameter of a container type takes the entry of its id, as on any parameter.

The container is never a lazy proxy of itself — it is already built, so there is nothing to defer. `lazy()` on one of these ids, or `#[Lazy]` on a parameter of that type, yields the container; the mark applies once the id is registered.

## Optional Dependencies

A dependency the container does not know — an interface that is neither bound nor registered, an id that is not registered — leaves a parameter with a default value at that default:

```php
final class Checkout
{
    public function __construct(
        private ?PaymentGateway $gateway = null,
        private Clock $clock = new SystemClock(),
    ) {
    }
}

$checkout = $container->get(Checkout::class); // $gateway is null, $clock a new SystemClock
```

Once `PaymentGateway` is bound or registered, it is injected. A dependency the container knows but cannot build — its own dependency is missing, its factory returns the wrong type — still throws: only an unknown dependency falls back.

## Other Parameters

Built-in types (`string`, `int`, `array`, …), union and intersection types and untyped parameters are never looked up. They need a default value or `#[Inject]`:

```php
use Solo\Container\Attribute\Inject;

final class Mailer
{
    public function __construct(
        private Transport $transport,
        #[Inject('mail.from')] private string $from,
        private int $retries = 3,
    ) {
    }
}
```

## Failures

| Situation | Exception |
|---|---|
| A required class or interface parameter the container does not know | `ContainerException`: `Cannot resolve Mailer: Service 'Transport' not found in container.` |
| `get()` of an abstract class, an enum, or a class with a non-public constructor | `ContainerException`: `Class 'X' is not instantiable.` |
| `get()` of an interface that is neither bound nor registered, other than [the container's own](#the-container-itself) | `NotFoundException` |
| A parameter with no class type, no default and no `#[Inject]` | `ContainerException`: `Cannot resolve parameter '$x' in class 'Y'.` |
| A class that depends on itself, directly or through others | `ContainerException`: `Circular dependency detected: …` — see [Circular Dependencies](/features/circular-dependencies) |

A missing dependency deeper in the graph names the whole chain — `Cannot resolve A -> B: Service 'C' not found in container.` — and keeps the `NotFoundException` as its previous exception.
