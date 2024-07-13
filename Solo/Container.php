<?php declare(strict_types=1);

namespace Solo;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Exception;

class Container implements ContainerInterface
{
    private array $services = [];

    public function __construct(array $services = [])
    {
        if (!empty($services)) {
            $this->setMultiple($services);
        }
    }

    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
    }

    public function setMultiple(array $services): void
    {
        foreach ($services as $id => $factory) {
            $this->set($id, $factory);
        }
    }

    public function get($id)
    {
        if ($this->has($id)) {
            return $this->services[$id]($this);
        }

        try {
            $reflector = new ReflectionClass($id);
        } catch (ReflectionException $e) {
            throw new Exception("Target class [$id] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new Exception("Target [$id] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $id;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } elseif ($parameter->isVariadic()) {
                    $dependencies[] = [];
                } else {
                    throw new Exception("Unresolvable dependency [$parameter] in class {$parameter->getDeclaringClass()->getName()}");
                }
            }

            $name = $type->getName();

            try {
                $dependency = $this->get($name);
                $dependencies[] = $dependency;
            } catch (Exception $e) {
                if ($parameter->isOptional()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    $dependency = $this->get($parameter->getType()->getName());
                    $this->set($name, $dependency);
                    $dependencies[] = $dependency;
                }
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    public function has($id): bool
    {
        return isset($this->services[$id]);
    }
}