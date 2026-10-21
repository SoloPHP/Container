<?php

declare(strict_types=1);

namespace Solo\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Solo\Container\Attribute\Inject;
use Solo\Container\Attribute\Lazy;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Container\Exceptions\ContainerException;
use Solo\Container\Exceptions\NotFoundException;

/** @no-named-arguments */
final class Container implements WritableContainerInterface
{
    /** @var array<string, callable> */
    private array $services = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, class-string> */
    private array $bindings = [];

    /** @var array<string, true> */
    private array $lazy = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /** @param array<string, callable> $services */
    public function __construct(array $services = [])
    {
        $this->services = $services;
    }

    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function reset(): void
    {
        $this->instances = [];
    }

    /** @param class-string $concrete */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
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
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        return $this->instances[$id] = isset($this->lazy[$id])
            ? $this->lazyProxy($id, fn(): object => $this->build($id))
            : $this->build($id);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function build(string $id): mixed
    {
        if (isset($this->resolving[$id])) {
            $chain = implode(' -> ', [...array_keys($this->resolving), $id]);
            throw new ContainerException("Circular dependency detected: $chain");
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

            throw new NotFoundException("Service '$id' not found in container.");
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * Resolve an id to a lazy proxy of the concrete class it maps to.
     *
     * The proxy runs $initializer on first use. Callers pass build() for an
     * id-level proxy — it IS the cached instance, so it must bypass the cache —
     * or get() for a #[Lazy] parameter, a transient reference that should reach
     * the shared singleton.
     *
     * @param callable(): object $initializer
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function lazyProxy(string $id, callable $initializer): object
    {
        $class = $this->concreteClass($id);

        if ($class === null) {
            if (!$this->has($id)) {
                throw new NotFoundException("Service '$id' not found in container.");
            }

            throw new ContainerException(
                "Cannot create a lazy proxy for '$id': it does not resolve to an instantiable class."
            );
        }

        return $this->newProxy($class, $initializer);
    }

    /**
     * @param class-string $class
     * @param callable(): object $initializer
     * @throws ContainerException
     */
    private function newProxy(string $class, callable $initializer): object
    {
        // A proxy must forward to a real instance, so when the initializer yields
        // another lazy object (an id marked lazy()) it gets the instance behind it.
        $forward = static function () use ($initializer): object {
            $instance = $initializer();

            return (new ReflectionClass($instance))->initializeLazyObject($instance);
        };

        try {
            return $this->reflect($class)->newLazyProxy($forward);
        } catch (\Error $e) {
            throw new ContainerException(
                "Cannot create a lazy proxy for '$class': {$e->getMessage()}.",
                0,
                $e
            );
        }
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
     * @throws ContainerException
     */
    private function resolve(string $id): object
    {
        $reflector = $this->reflect($id);

        $constructor = $reflector->getConstructor();
        if (!$constructor) {
            return new $id();
        }

        return $reflector->newInstanceArgs(
            array_map($this->resolveParameter(...), $constructor->getParameters())
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

    /** @throws ContainerException */
    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $name = $this->classType($param);
        // #[Lazy] only applies to class/interface dependencies; on any other
        // parameter shape it is silently ignored.
        $lazy = $name !== null && $param->getAttributes(Lazy::class) !== [];
        $id = $this->injectedId($param);

        if ($id !== null) {
            return $this->resolveById($param, $id, $name, $lazy);
        }

        if ($name !== null) {
            if ($lazy) {
                return $this->lazyProxy($name, fn(): object => $this->get($name));
            }

            return $this->get($name);
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new ContainerException("Cannot resolve parameter {$this->describe($param)}.");
    }

    /**
     * The class or interface a parameter is typed with, or null for an untyped,
     * builtin, union or intersection type.
     */
    private function classType(ReflectionParameter $param): ?string
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        // `self` and `parent` are relative to the declaring class, not class names of their own.
        return match (strtolower($name)) {
            'self' => $param->getDeclaringClass()?->getName(),
            'parent' => ($param->getDeclaringClass()?->getParentClass() ?: null)?->getName(),
            default => $name,
        };
    }

    /** @throws ContainerException */
    private function injectedId(ReflectionParameter $param): ?string
    {
        try {
            return ($param->getAttributes(Inject::class)[0] ?? null)?->newInstance()->id;
        } catch (\Error $e) {
            throw new ContainerException(
                "Invalid #[Inject] on {$this->describe($param)}: {$e->getMessage()}.",
                0,
                $e
            );
        }
    }

    /**
     * Resolve an #[Inject] parameter from its service id. A class or interface type is
     * checked against what the id yields; with #[Lazy] the parameter gets a proxy of the
     * class that type resolves to, which resolves the id on first use. Other types take
     * the value as it is.
     *
     * @throws NotFoundException
     * @throws ContainerException
     */
    private function resolveById(ReflectionParameter $param, string $id, ?string $type, bool $lazy): mixed
    {
        if ($type === null) {
            return $this->get($id);
        }

        if ($lazy) {
            $at = $this->describe($param);
            $class = $this->concreteClass($type) ?? throw new ContainerException(
                "Cannot create a lazy proxy for $at: type '$type' does not resolve to an instantiable class."
            );

            // A proxy forwards only to an instance of exactly its own class: not a
            // subclass, and never null.
            return $this->newProxy($class, function () use ($id, $at, $class): object {
                $value = $this->get($id);

                return is_object($value) && $value::class === $class
                    ? $value
                    : throw $this->mismatch($id, $at, $value, $class);
            });
        }

        $value = $this->get($id);

        return $value instanceof $type || ($value === null && $param->allowsNull())
            ? $value
            : throw $this->mismatch($id, $this->describe($param), $value, $type);
    }

    private function mismatch(string $id, string $at, mixed $value, string $type): ContainerException
    {
        return new ContainerException(
            "Service '$id' injected into $at is " . get_debug_type($value) . ", not $type."
        );
    }

    private function describe(ReflectionParameter $param): string
    {
        $class = $param->getDeclaringClass()?->getName() ?? 'unknown';

        return "'\${$param->getName()}' in class '$class'";
    }
}
