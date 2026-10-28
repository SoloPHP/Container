<?php

declare(strict_types=1);

namespace Solo\Tests\PHPStan;

use Psr\Container\ContainerInterface;
use Solo\Container\Container;
use Solo\Tests\Fixtures\AbstractService;
use Solo\Tests\Fixtures\ClassWithDependency;
use stdClass;

use function PHPStan\Testing\assertType;

/**
 * What Container::get() is inferred as. Analysed at level max with the rest of the
 * package; a wrong assertType() fails the run.
 */
final class ContainerTypes
{
    /**
     * @param class-string<ClassWithDependency> $class
     * @param class-string $anyClass
     */
    public function classIds(Container $container, string $class, string $anyClass, bool $flag): void
    {
        assertType(ClassWithDependency::class, $container->get(ClassWithDependency::class));
        assertType(AbstractService::class, $container->get(AbstractService::class));
        assertType(ContainerInterface::class, $container->get(ContainerInterface::class));
        assertType(stdClass::class, $container->get('stdClass'));
        assertType(ClassWithDependency::class, $container->get($class));
        assertType('object', $container->get($anyClass));
        assertType(
            'Solo\Tests\Fixtures\ClassWithDependency|stdClass',
            $container->get($flag ? ClassWithDependency::class : stdClass::class),
        );
    }

    public function otherIds(Container $container, string $id, bool $flag): void
    {
        assertType('mixed', $container->get('app.name'));
        assertType('mixed', $container->get('App\Missing'));
        assertType('mixed', $container->get($id));
        assertType('mixed', $container->get($flag ? stdClass::class : 'app.name'));
    }

    public function factories(): Container
    {
        $container = new Container([
            'config' => function ($c) {
                assertType(Container::class, $c);

                return new stdClass();
            },
        ]);

        $container->set(ClassWithDependency::class, function ($c) {
            assertType(Container::class, $c);

            return new ClassWithDependency($c->get(stdClass::class));
        });

        return $container;
    }

    /**
     * PSR-11 promises nothing about the class of an entry, so code typed against it
     * keeps the mixed get() of psr/container.
     */
    public function psr(ContainerInterface $container): void
    {
        assertType('mixed', $container->get(stdClass::class));
    }
}
