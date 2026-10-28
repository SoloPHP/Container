<?php

declare(strict_types=1);

namespace Solo\Tests\PHPStan;

use Solo\Contracts\Container\WritableContainerInterface;
use Solo\Tests\Fixtures\ClassWithDependency;
use Solo\Tests\Fixtures\NeedsInjectedServices;
use Solo\Tests\Fixtures\NeedsLazyDependency;
use stdClass;

use function PHPStan\Testing\assertType;

/**
 * What get() is inferred as through WritableContainerInterface, as service providers
 * receive the container. Analysed at level max with the rest of the package.
 */
final class ContractTypes
{
    public function get(WritableContainerInterface $container, string $id): void
    {
        assertType(ClassWithDependency::class, $container->get(ClassWithDependency::class));
        assertType(WritableContainerInterface::class, $container->get(WritableContainerInterface::class));
        assertType('mixed', $container->get('app.name'));
        assertType('mixed', $container->get($id));
        assertType('bool', $container->has('app.name'));
    }

    /**
     * A service provider in the usual style: at level max it passes only because each
     * untyped factory parameter is inferred as the container and each get() as its class.
     */
    public function register(WritableContainerInterface $container): void
    {
        $container->set(NeedsLazyDependency::class, function ($c) {
            assertType(WritableContainerInterface::class, $c);

            return new NeedsLazyDependency($c->get(ClassWithDependency::class));
        });

        $container->set(NeedsInjectedServices::class, fn($c) => new NeedsInjectedServices(
            $c->get(ClassWithDependency::class)->dependency,
            $c->get(stdClass::class),
        ));

        $container->set(
            ClassWithDependency::class,
            fn(WritableContainerInterface $c) => new ClassWithDependency($c->get(stdClass::class)),
        );
    }
}
