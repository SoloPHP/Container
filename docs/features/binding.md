# Interface Binding

`bind()` makes an id resolve to a class:

```php
$container->bind(LoggerInterface::class, FileLogger::class);
$container->bind(CacheInterface::class, RedisCache::class);

$container->get(LoggerInterface::class); // FileLogger
```

The class is resolved like any other id — its own factory, its own binding, or auto-wiring — so a binding and a factory combine:

```php
$container->bind(LoggerInterface::class, FileLogger::class);
$container->set(FileLogger::class, fn() => new FileLogger('/var/log/app.log'));
```

The instance is shared between both ids:

```php
$container->get(LoggerInterface::class) === $container->get(FileLogger::class); // true
```

[Auto-wiring](/features/autowiring) goes through the same bindings: a parameter typed `LoggerInterface` receives the `FileLogger`. Any id can be bound, not only an interface name: `bind('logger', FileLogger::class)`.

## Compatible Types

When the abstract is a class or interface name, the bound class must implement or extend it. Otherwise resolving the id throws `ContainerException`:

```php
$container->bind(LoggerInterface::class, Mailer::class);

$container->get(LoggerInterface::class);
// ContainerException: Service 'LoggerInterface' is Mailer, not an instance of LoggerInterface.
```

A binding to a class that does not exist throws `ContainerException` as well, naming the missing class.

## Replacing a Registration

`bind()` replaces the id's factory or earlier binding and drops the instance cached for it, as `set()` does — the last registration of an id wins:

```php
$container->set('logger', fn() => new NullLogger());
$container->bind('logger', FileLogger::class);

$container->get('logger'); // FileLogger
```

## Cycles

A class bound to itself, or two bindings pointing at each other, fail on `get()` with `ContainerException`:

```php
$container->bind(ServiceA::class, ServiceB::class);
$container->bind(ServiceB::class, ServiceA::class);

$container->get(ServiceA::class);
// ContainerException: Circular dependency detected: ServiceA -> ServiceB -> ServiceA
```
