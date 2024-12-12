<?php declare(strict_types=1);

namespace Solo;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Solo\Exceptions\ContainerException;
use Solo\Exceptions\NotFoundException;

/**
 * PSR-11 compatible Dependency Injection Container implementation
 */
class Container implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $services = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, string> */
    private array $bindings = [];

    /**
     * Container constructor.
     *
     * @param array<string, callable> $initialServices Initial services to register
     * @throws ContainerException If any of the initial services is not callable
     */
    public function __construct(array $initialServices = [])
    {
        $this->setMultiple($initialServices);
    }

    /**
     * Register multiple services at once
     *
     * @param array<string, callable> $services Array of service factories
     * @throws ContainerException If any service is not callable
     */
    public function setMultiple(array $services): void
    {
        foreach ($services as $id => $factory) {
            if (!is_callable($factory)) {
                throw new ContainerException("Service [$id] must be a callable.");
            }

            $this->set($id, $factory);
        }
    }

    /**
     * Register a service factory
     *
     * @param string $id Service identifier
     * @param callable $factory Service factory callable
     */
    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = fn($c) => $factory($c);
    }

    /**
     * Bind an abstract type to a concrete implementation
     *
     * @param string $abstract Abstract type identifier
     * @param string $concrete Concrete class name
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
     * @throws ReflectionException
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->services[$id])) {
            $resolved = $this->services[$id]($this);
        } elseif (isset($this->bindings[$id])) {
            $resolved = $this->resolve($this->bindings[$id]);
        } elseif (class_exists($id)) {
            $resolved = $this->resolve($id);
        } else {
            throw new NotFoundException("Service [$id] not found in the container.");
        }

        $this->instances[$id] = $resolved;
        return $resolved;
        }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    private function resolve(string $id)
    {
            $reflector = new ReflectionClass($id);

        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class [$id] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if (!$constructor) {
            return new $id;
        }

        $dependencies = array_map(function ($param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $this->get($type->getName());
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new ContainerException("Cannot resolve dependency [{$param->getName()}].");
        }, $constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }
}