# Injection by Id

Auto-wiring resolves a parameter by its type. When the type cannot tell dependencies apart — several instances of one class, configuration values — put `#[Inject]` on the parameter to resolve it from a service id instead:

```php
use Solo\Container\Attribute\Inject;

$container->set('db.primary', fn() => new Connection($primaryDsn));
$container->set('db.replica', fn() => new Connection($replicaDsn));
$container->set('app.name', fn() => 'Shop');

final class Reports
{
    public function __construct(
        #[Inject('db.primary')] private Connection $writes,
        #[Inject('db.replica')] private Connection $reads,
        #[Inject('app.name')] private string $appName,
    ) {
    }
}
```

The parameter receives what `get($id)` returns, so it shares the singleton.

## Unknown Ids

An id that is not registered leaves a parameter with a default value at that default:

```php
final class Reports
{
    public function __construct(#[Inject('reports.title')] private string $title = 'Reports')
    {
    }
}
```

Without a default, building the class throws `ContainerException`:

```text
Cannot resolve Reports: Service 'reports.title' not found in container.
```

## Type Checks

A class or interface type is checked against the value: another type, or `null` for a non-nullable parameter, throws `ContainerException`:

```text
Service 'db.primary' injected into '$writes' in class 'Reports' is stdClass, not Connection.
```

A nullable parameter accepts `null`. Other types — `string`, `array`, unions — take the value as it is and leave the check to PHP.

## Lazy Injection

With `#[Lazy]`, the parameter receives a proxy that resolves the id on first use:

```php
use Solo\Container\Attribute\{Inject, Lazy};

final class Reports
{
    public function __construct(#[Inject('db.replica')] #[Lazy] private Connection $reads)
    {
    }
}
```

The id must be registered when `Reports` is built; an unknown one fails then, or takes the parameter's default, as without `#[Lazy]`. The proxy is built from the class the declared type resolves to, directly or through a binding, so that class must be instantiable, and the id must yield an instance of exactly it — a subclass, another implementation or `null` throws `ContainerException` on first use.

## Invalid Attributes

`#[Inject]` without an id, or repeated on one parameter, throws `ContainerException` naming the parameter:

```text
Invalid #[Inject] on '$service' in class 'Reports': …
```
