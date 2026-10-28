<?php

declare(strict_types=1);

namespace Solo\Container;

use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Container\Exceptions\ContainerException;
use Solo\Container\Exceptions\NotFoundException;
use Solo\Container\Internal\LazyProxy;
use Solo\Container\Internal\Parameter;

/** @no-named-arguments */
final class Container implements WritableContainerInterface
{
    /** @var array<string, callable(self): mixed> */
    private array $services = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, class-string> */
    private array $bindings = [];

    /** @var array<string, true> */
    private array $lazy = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /** @param array<string, callable(self): mixed> $services */
    public function __construct(array $services = [])
    {
        foreach ($services as $id => $factory) {
            $this->set((string) $id, $factory);
        }
    }

    /**
     * Register the factory of an id. It replaces the id's factory or binding and
     * drops the instance cached for it.
     *
     * @param callable(self): mixed $factory
     */
    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
        unset($this->bindings[$id], $this->instances[$id]);
    }

    public function reset(): void
    {
        $this->instances = [];
    }

    /**
     * Make an id resolve to a class. It replaces the id's factory or binding and
     * drops the instance cached for it.
     *
     * @param class-string $concrete
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->services[$abstract], $this->instances[$abstract]);
    }

    /**
     * Mark an id so it always resolves to a lazy proxy.
     *
     * The proxy stands in for the real instance and builds it on first use,
     * which lets the container break a circular dependency: the dependent
     * constructor receives the proxy and completes without re-entering
     * resolution. The id must resolve (directly or through a binding) to an
     * instantiable class. For per-injection-point laziness use the #[Lazy]
     * attribute on the constructor parameter instead.
     */
    public function lazy(string $id): void
    {
        $this->lazy[$id] = true;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->bindings[$id]) || class_exists($id);
    }

    /**
     * An id that names a class or interface resolves to an instance of it: a
     * factory or binding that yields anything else throws ContainerException.
     * Any other id resolves to whatever was registered for it.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        // An id marked lazy() is cached as its proxy, so the proxy builds the entry
        // with build(), which bypasses the cache.
        return $this->instances[$id] = $this->checked($id, isset($this->lazy[$id])
            ? $this->lazyProxy($id, $id, "'$id'", fn(): mixed => $this->build($id))
            : $this->build($id));
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function build(string $id): mixed
    {
        if (isset($this->resolving[$id])) {
            throw new ContainerException("Circular dependency detected: {$this->chain($id)}");
        }

        $this->resolving[$id] = true;

        try {
            if (isset($this->services[$id])) {
                return $this->services[$id]($this);
            }

            if (isset($this->bindings[$id])) {
                return $this->get($this->bindings[$id]);
            }

            if (class_exists($id)) {
                return $this->resolve($id);
            }
        } catch (NotFoundExceptionInterface $e) {
            // A missing dependency is an error resolving this id, not a missing entry (PSR-11).
            throw new ContainerException("Cannot resolve {$this->chain()}: {$e->getMessage()}", 0, $e);
        } finally {
            unset($this->resolving[$id]);
        }

        throw $this->notFound($id);
    }

    /** The ids being resolved, outermost first, followed by $tail. */
    private function chain(string ...$tail): string
    {
        return implode(' -> ', [...array_keys($this->resolving), ...$tail]);
    }

    private function notFound(string $id): NotFoundException
    {
        return new NotFoundException("Service '$id' not found in container.");
    }

    /**
     * An id that names a class or interface must resolve to an instance of it.
     *
     * @throws ContainerException
     */
    private function checked(string $id, mixed $value): mixed
    {
        // class_exists() has run the autoloader for $id, so interface_exists() need not.
        if ($value instanceof $id || !(class_exists($id) || interface_exists($id, false))) {
            return $value;
        }

        throw new ContainerException("Service '$id' is " . get_debug_type($value) . ", not an instance of $id.");
    }

    /**
     * A lazy proxy of the class $type resolves to, directly or through a binding,
     * standing in for the entry $id. It runs $initializer on first use; $at names
     * it in errors.
     *
     * @param callable(): mixed $initializer
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function lazyProxy(string $id, string $type, string $at, callable $initializer): object
    {
        if (!$this->has($id)) {
            throw $this->notFound($id);
        }

        $class = $this->concreteClass($type) ?? throw new ContainerException(
            "Cannot create a lazy proxy for $at: '$type' does not resolve to an instantiable class."
        );

        return LazyProxy::create($this->reflect($class), $at, $initializer);
    }

    /**
     * Follow the binding chain to the concrete class an id resolves to, or null
     * if it is not (and is not bound to) an existing class.
     *
     * @return class-string|null
     */
    private function concreteClass(string $id): ?string
    {
        $seen = [];

        while (isset($this->bindings[$id])) {
            if (isset($seen[$id])) {
                return null;
            }
            $seen[$id] = true;
            $id = $this->bindings[$id];
        }

        return class_exists($id) ? $id : null;
    }

    /**
     * @param class-string $id
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function resolve(string $id): object
    {
        $reflector = $this->reflect($id);

        return $reflector->newInstanceArgs(
            array_map($this->resolveParameter(...), Parameter::ofConstructor($reflector))
        );
    }

    /**
     * @param class-string $class
     * @return ReflectionClass<object>
     * @throws ContainerException
     */
    private function reflect(string $class): ReflectionClass
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class '$class' is not instantiable.");
        }

        return $reflection;
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function resolveParameter(Parameter $param): mixed
    {
        try {
            return $this->resolveDependency($param);
        } catch (NotFoundException $e) {
            // An unknown dependency leaves an optional parameter at its default value.
            return $param->hasDefaultValue() ? $param->defaultValue() : throw $e;
        }
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function resolveDependency(Parameter $param): mixed
    {
        $type = $param->type;

        // #[Lazy] applies only to a class or interface type; on any other parameter it is ignored.
        if ($type !== null && $param->lazy) {
            // A transient reference: on first use it reaches the shared entry of the
            // #[Inject] id, or of the type, through get().
            $target = $param->id ?? $type;
            $proxy = $this->lazyProxy($target, $type, $param->description(), fn(): mixed => $this->get($target));

            return $this->injected($param, $target, $proxy);
        }

        if ($param->id !== null) {
            return $this->injected($param, $param->id, $this->get($param->id));
        }

        if ($type !== null) {
            return $this->get($type);
        }

        if ($param->hasDefaultValue()) {
            return $param->defaultValue();
        }

        throw new ContainerException("Cannot resolve parameter {$param->description()}.");
    }

    /**
     * $value, resolved from the id $id, if the parameter takes it.
     *
     * @throws ContainerException
     */
    private function injected(Parameter $param, string $id, mixed $value): mixed
    {
        if ($param->accepts($value)) {
            return $value;
        }

        throw new ContainerException(
            "Service '$id' injected into {$param->description()} is " . get_debug_type($value) . ", not {$param->type}."
        );
    }
}
