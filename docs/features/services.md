# Services & Factories

## Registering a Factory

```php
$container->set(Mailer::class, fn($c) => new Mailer($c->get(Transport::class)));
```

A factory is any callable. It runs on the first `get()` of its id and receives the container; a factory that needs nothing can leave the argument out: `fn() => new Clock()`.

The constructor takes factories as an array keyed by id and registers each of them with `set()`, so a value that is not callable throws `TypeError` right away:

```php
$container = new Container([
    'config' => fn() => new Config('config.php'),
    'cache'  => fn($c) => new Cache($c->get('config')),
]);
```

## Ids and Values

An id is any string. A class or interface name is the usual one — it is what [auto-wiring](/features/autowiring) asks the container for — and a name of your own suits a value that no type identifies:

```php
$container->set('app.name', fn() => 'Shop');
$container->set('db.replica', fn() => new Connection($replicaDsn));
```

Under a name of your own, a factory may return any value, and `null` is cached like any other. Under a class or interface name, it must return an instance of that class — the class itself or a subclass, or for an interface an implementation. Anything else throws `ContainerException` when the id is resolved:

```php
$container->set(LoggerInterface::class, fn() => new FileLogger()); // fine
$container->set(Clock::class, fn() => null);

$container->get(Clock::class);
// ContainerException: Service 'Clock' is null, not an instance of Clock.
```

This is what makes `get(Foo::class)` safe to use as a `Foo`, for you and for [static analysis](/guide/static-analysis).

An exception thrown by a factory reaches the caller of `get()` unchanged — except a PSR-11 `NotFoundExceptionInterface`, which becomes a `ContainerException`: the id is known, one of its dependencies is not.

## Shared Instances

Each id is built once and cached: every later `get()` returns the same value, and every service that depends on the id receives the same instance.

```php
$container->set('clock', fn() => new SystemClock());

$container->get('clock') === $container->get('clock'); // true
```

## One Registration per Id

An id has one registration: a factory or a [binding](/features/binding). `set()` and `bind()` replace whatever the id had and drop its cached instance, so the next `get()` uses the new registration:

```php
$container->set(Connection::class, fn() => new Connection('db1'));
$conn1 = $container->get(Connection::class); // Connection to db1

$container->set(Connection::class, fn() => new Connection('db2'));
$conn2 = $container->get(Connection::class); // Connection to db2
```

Services built earlier keep the instance they received.

## Resetting All Instances

`reset()` drops every cached instance, so the whole graph is built again on demand. Factories, bindings and lazy marks stay:

```php
$container->reset();
```

Use it when a root dependency changes, between tests, or between the jobs of a long-running worker.

## Checking an Id

`has()` tells whether the container knows how to resolve an id: the id has a factory or a binding, or it names an existing class.

```php
$container->has('app.name');             // true — it has a factory
$container->has(UserRepository::class);  // true — an existing class, auto-wired
$container->has(LoggerInterface::class); // false until it is bound or registered
```

As PSR-11 allows, `has()` returning `true` does not mean `get()` succeeds: an abstract class, or a class with a dependency that cannot be resolved, throws `ContainerException`.

## Resolution Order

`get()` returns, in this order:

1. the instance already cached for the id;
2. for an id marked [`lazy()`](/features/lazy), a lazy proxy, which goes through the steps below on first use;
3. the result of the id's factory, or of its binding;
4. for an existing class, an [auto-wired](/features/autowiring) instance;
5. otherwise it throws `NotFoundException`.
