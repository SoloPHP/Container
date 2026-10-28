<?php

declare(strict_types=1);

namespace Solo\Container\Internal;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Solo\Container\Attribute\Inject;
use Solo\Container\Attribute\Lazy;
use Solo\Container\Exceptions\ContainerException;

/**
 * A constructor parameter as the container sees it, read from reflection.
 *
 * @internal
 */
final readonly class Parameter
{
    /**
     * @param string|null $type the class or interface the parameter is typed with
     * @param string|null $id the service id of its #[Inject]
     * @param bool $lazy whether it carries #[Lazy]
     */
    private function __construct(
        public ?string $type,
        public ?string $id,
        public bool $lazy,
        private ReflectionParameter $reflection,
    ) {
    }

    /**
     * The parameters of a class constructor, in order.
     *
     * @param ReflectionClass<object> $class
     * @return list<self>
     * @throws ContainerException
     */
    public static function ofConstructor(ReflectionClass $class): array
    {
        return array_map(self::of(...), $class->getConstructor()?->getParameters() ?? []);
    }

    /** Whether the parameter takes $value: one with a class or interface type takes an instance of it. */
    public function accepts(mixed $value): bool
    {
        $type = $this->type;

        return $type === null || $value instanceof $type || ($value === null && $this->reflection->allowsNull());
    }

    public function hasDefaultValue(): bool
    {
        return $this->reflection->isDefaultValueAvailable();
    }

    /** The default value, evaluated on each call as PHP does: `= new Foo()` yields a new Foo. */
    public function defaultValue(): mixed
    {
        return $this->reflection->getDefaultValue();
    }

    /** How errors name the parameter: '$name' in class 'Foo'. */
    public function description(): string
    {
        return self::describe($this->reflection);
    }

    /** @throws ContainerException */
    private static function of(ReflectionParameter $param): self
    {
        return new self(
            self::classType($param),
            self::injectedId($param),
            $param->getAttributes(Lazy::class) !== [],
            $param,
        );
    }

    /**
     * The class or interface a parameter is typed with, or null for an untyped,
     * builtin, union or intersection type.
     */
    private static function classType(ReflectionParameter $param): ?string
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
    private static function injectedId(ReflectionParameter $param): ?string
    {
        try {
            return ($param->getAttributes(Inject::class)[0] ?? null)?->newInstance()->id;
        } catch (\Error $e) {
            throw new ContainerException(
                'Invalid #[Inject] on ' . self::describe($param) . ": {$e->getMessage()}.",
                0,
                $e
            );
        }
    }

    private static function describe(ReflectionParameter $param): string
    {
        $class = $param->getDeclaringClass()?->getName() ?? 'unknown';

        return "'\${$param->getName()}' in class '$class'";
    }
}
