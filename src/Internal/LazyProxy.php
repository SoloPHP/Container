<?php

declare(strict_types=1);

namespace Solo\Container\Internal;

use ReflectionClass;
use Solo\Container\Exceptions\ContainerException;

/**
 * Native PHP 8.4 lazy proxies, with the rules PHP imposes made explicit: a proxy
 * forwards only to an instance of exactly its own class, and a class without
 * instance properties cannot be deferred at all.
 *
 * @internal
 */
final class LazyProxy
{
    /**
     * A proxy of $class that runs $initializer on first use, or, for a class PHP
     * cannot defer, the instance $initializer yields right away. $at names the
     * proxy in errors.
     *
     * @param ReflectionClass<object> $class an instantiable class
     * @param callable(): mixed $initializer
     * @throws ContainerException
     */
    public static function create(ReflectionClass $class, string $at, callable $initializer): object
    {
        $name = $class->getName();

        // When the initializer yields another lazy object (an id marked lazy()),
        // the proxy gets the instance behind it.
        $forward = static function () use ($initializer, $class, $name, $at): object {
            $value = $initializer();

            if (!is_object($value) || $value::class !== $name) {
                throw new ContainerException(
                    "Cannot initialize the lazy proxy for $at: it resolved to " . get_debug_type($value)
                    . ", not an instance of exactly $name."
                );
            }

            return $class->initializeLazyObject($value);
        };

        try {
            $proxy = $class->newLazyProxy($forward);
        } catch (\Error $e) {
            throw new ContainerException("Cannot create a lazy proxy for $at: {$e->getMessage()}.", 0, $e);
        }

        // PHP marks the proxy of a class without instance properties as initialized
        // at once, without ever running the initializer: build the instance now.
        return $class->isUninitializedLazyObject($proxy) ? $proxy : $forward();
    }
}
