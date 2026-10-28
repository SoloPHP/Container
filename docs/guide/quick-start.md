# Quick Start

## Create the Container

```php
use Solo\Container\Container;

$container = new Container();
```

## Register Services

A factory builds a service. It receives the container, to fetch what the service needs:

```php
$container->set(Config::class, fn() => new Config(__DIR__ . '/config.php'));
$container->set(Database::class, fn($c) => new Database($c->get(Config::class)->dsn));
```

An id is a string: usually a class or interface name, or a name of your own for a value no type stands for:

```php
$container->set('app.name', fn() => 'Shop');
```

## Bind Interfaces

```php
$container->bind(LoggerInterface::class, FileLogger::class);
```

`get(LoggerInterface::class)` now resolves `FileLogger`.

## Resolve Services

```php
$users = $container->get(UserRepository::class);
```

`UserRepository` has no registration, so the container builds it from its constructor:

```php
final class UserRepository
{
    public function __construct(
        private Database $database,      // from the Database factory
        private LoggerInterface $logger, // a FileLogger, through the binding
    ) {
    }
}
```

Each id is built once; later calls return the same instance:

```php
$container->get(UserRepository::class) === $container->get(UserRepository::class); // true
```

## Service Providers

An application often groups its registrations in service providers. Typing them against `WritableContainerInterface`, which adds `set()` to PSR-11, keeps them independent of this implementation:

```php
use Solo\Contracts\Container\WritableContainerInterface;

final class DatabaseServiceProvider
{
    public function register(WritableContainerInterface $container): void
    {
        $container->set(Connection::class, fn($c) => new Connection(
            $c->get(Config::class)->dsn,
        ));
    }
}
```

PHPStan infers the untyped `$c` as the container and `$c->get(Config::class)` as a `Config` — see [Static Analysis](/guide/static-analysis).

## Handle Errors

```php
use Psr\Container\NotFoundExceptionInterface;

try {
    $mailer = $container->get('mailer');
} catch (NotFoundExceptionInterface $e) {
    // Service 'mailer' not found in container.
}
```

`NotFoundException` means the container does not know the id; `ContainerException` means it knows the id but cannot build it. See [Exceptions](/api/exceptions).

## Next Steps

- [Services & Factories](/features/services) — Registering, replacing and resetting services
- [Auto-wiring](/features/autowiring) — How constructor parameters are resolved
- [Lazy Services](/features/lazy) — Deferred construction with native proxies
