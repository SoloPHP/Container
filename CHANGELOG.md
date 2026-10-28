# Changelog

## 4.0.0 — 2026-10-28

The container is strict and its types are guaranteed: an id that names a class resolves to an instance of that class or fails, each id has one registration, and a missing dependency is reported as an error building the service that needs it. `get(Foo::class)` is a `Foo` to PHPStan, so code analysed at level max needs no cast after it. See the [upgrade guide](https://solophp.github.io/Container/guide/upgrading).

### Breaking changes

- **Class names resolve to instances.** A factory or binding registered under a class or interface name must yield an instance of it; anything else — another class, `null`, a string — throws `ContainerException`: `Service 'X' is Y, not an instance of X.` Ids that are not class names are not checked.
- **One registration per id.** `set()` and `bind()` replace each other: the last registration of an id wins, where a factory used to win over a binding whatever the order. `bind()` now drops the instance cached for the id, as `set()` and `lazy()` do.
- **Missing dependencies are container errors (PSR-11).** `NotFoundException` means only that the id passed to `get()` is unknown. A dependency missing while an entry is built throws `ContainerException` — `Cannot resolve A -> B: Service 'C' not found in container.` — with the `NotFoundException` as its previous exception.
- **Optional dependencies take their default.** A class, interface or `#[Inject]` dependency the container does not know leaves a parameter with a default value at that default, where it used to throw. A known dependency that cannot be built still throws.
- **Lazy services.** A lazy id whose factory yields anything but an instance of exactly the proxied class throws `ContainerException` on first use, instead of a PHP `TypeError`. A lazy id bound to a class that is not an instance of the id throws at `get()`. An unregistered `#[Inject]` id with `#[Lazy]` fails when the dependent class is built, not on first use. A class without properties is built at once: PHP cannot defer it, and 3.x returned a proxy whose factory and constructor never ran.
- **Constructor.** `new Container([...])` registers each factory with `set()`, so a value that is not callable throws `TypeError` at construction.
- Requires `solophp/contracts` ^1.7.

### Added

- **Typed `get()`** — `get(Foo::class)` and `get($class)` with a `class-string<Foo>` are `Foo`, a plain `class-string` is `object`, any other id is `mixed`. `WritableContainerInterface` from `solophp/contracts` 1.7 declares the same, so service providers typed against it are covered with no configuration.
- Factories given to the constructor and to `set()` are typed `callable(Container): mixed`: PHPStan infers an untyped factory parameter as the container and reports a factory that cannot take it.
- The package is analysed at PHPStan level max, with fixtures that check the inferred types.
- [Documentation site](https://solophp.github.io/Container/).

### Changed

- Reading constructor parameters and creating lazy proxies moved into `Solo\Container\Internal\Parameter` and `Solo\Container\Internal\LazyProxy`. Both are `@internal`, not part of the public API.
- The Composer archive no longer contains `docs/`, `tests/` and the package's own tool configs.

Earlier releases are described in [GitHub Releases](https://github.com/SoloPHP/Container/releases).
