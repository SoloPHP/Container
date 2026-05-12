<?php

declare(strict_types=1);

namespace Solo\Container;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
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
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            $chain = implode(' -> ', [...array_keys($this->resolving), $id]);
            throw new ContainerException("Circular dependency detected: $chain");
        }

        $this->resolving[$id] = true;

        try {
            if (isset($this->services[$id])) {
                $resolved = $this->services[$id]($this);
            } elseif (isset($this->bindings[$id])) {
                $resolved = $this->get($this->bindings[$id]);
            } elseif (class_exists($id)) {
                $resolved = $this->resolve($id);
            } else {
                throw new NotFoundException("Service '$id' not found in container.");
            }
        } finally {
            unset($this->resolving[$id]);
        }

        $this->instances[$id] = $resolved;

        return $resolved;
    }

    /**
     * @param class-string $id
     * @throws ContainerException
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

    /** @throws ContainerException */
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
