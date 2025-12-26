<?php

declare(strict_types=1);

namespace Solo\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Container\Exceptions\ContainerException;
use Solo\Container\Exceptions\NotFoundException;

/**
 * PSR-11 compatible Dependency Injection Container implementation
 */
class Container implements WritableContainerInterface
{
    /** @var array<string, callable> */
    private array $services = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, class-string> */
    private array $bindings = [];

    /** @param array<string, callable> $services */
    public function __construct(array $services = [])
    {
        foreach ($services as $id => $factory) {
            $this->services[$id] = $factory;
        }
    }

    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
    }

    /**
     * Bind an abstract type to a concrete implementation
     *
     * @param string $abstract Abstract type identifier
     * @param class-string $concrete Concrete class name
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Check if a service is registered
     *
     * @param string $id Service identifier
     * @return bool Whether the service exists
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->bindings[$id]) || class_exists($id);
    }

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     * @return mixed Resolved service
     * @throws NotFoundException If the service is not found
     * @throws ContainerException If the service cannot be resolved
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->services[$id])) {
            $resolved = $this->services[$id]($this);
        } elseif (isset($this->bindings[$id])) {
            $resolved = $this->get($this->bindings[$id]);
        } elseif (class_exists($id)) {
            $resolved = $this->resolve($id);
        } else {
            throw new NotFoundException("Service '$id' not found in container.");
        }

        $this->instances[$id] = $resolved;

        return $resolved;
    }

    /**
     * Resolve a class by reflection
     *
     * @param class-string $id Class name to resolve
     * @return object Resolved instance
     * @throws ContainerException If the class cannot be instantiated
     */
    private function resolve(string $id): object
    {
        $reflector = new ReflectionClass($id);

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class '$id' is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if (!$constructor) {
            return new $id();
        }

        return $reflector->newInstanceArgs(
            array_map($this->resolveParameter(...), $constructor->getParameters())
        );
    }

    /**
     * Resolve a single constructor parameter
     *
     * @throws ContainerException If the parameter cannot be resolved
     */
    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        $class = $param->getDeclaringClass()?->getName() ?? 'unknown';

        throw new ContainerException("Cannot resolve parameter '\${$param->getName()}' in class '$class'.");
    }
}
