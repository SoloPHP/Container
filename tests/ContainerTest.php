<?php

declare(strict_types=1);

namespace Solo\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Solo\Container\Container;
use Solo\Container\Exceptions\ContainerException;
use Solo\Container\Exceptions\NotFoundException;
use Solo\Tests\Fixtures\AbstractService;
use Solo\Tests\Fixtures\AttrCircularA;
use Solo\Tests\Fixtures\AttrCircularB;
use Solo\Tests\Fixtures\CircularA;
use Solo\Tests\Fixtures\CircularB;
use Solo\Tests\Fixtures\ClassWithDefaultParam;
use Solo\Tests\Fixtures\ClassWithDependency;
use Solo\Tests\Fixtures\ClassWithUnresolvable;
use Solo\Tests\Fixtures\NeedsLazyDependency;
use ReflectionClass;
use stdClass;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    private function assertLazyUninitialized(object $proxy): void
    {
        $this->assertTrue((new ReflectionClass($proxy::class))->isUninitializedLazyObject($proxy));
    }

    private function assertNotLazy(object $object): void
    {
        $this->assertFalse((new ReflectionClass($object::class))->isUninitializedLazyObject($object));
    }

    public function testImplementsPsr11Interface(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
    }

    public function testConstructorWithServices(): void
    {
        $container = new Container([
            'a' => fn() => 'value-a',
        ]);

        $this->assertEquals('value-a', $container->get('a'));
    }

    public function testSetGetAndSingleton(): void
    {
        $this->container->set('service', fn() => new stdClass());

        $this->assertTrue($this->container->has('service'));
        $this->assertSame(
            $this->container->get('service'),
            $this->container->get('service')
        );
    }

    public function testBind(): void
    {
        $this->container->bind(ContainerInterface::class, Container::class);

        $this->assertTrue($this->container->has(ContainerInterface::class));
        $this->assertInstanceOf(Container::class, $this->container->get(ContainerInterface::class));
    }

    public function testAutoResolveWithDependencies(): void
    {
        $this->assertTrue($this->container->has(ClassWithDependency::class));

        $resolved = $this->container->get(ClassWithDependency::class);

        $this->assertInstanceOf(stdClass::class, $resolved->dependency);
    }

    public function testAutoResolveClassWithoutConstructor(): void
    {
        $resolved = $this->container->get(stdClass::class);

        $this->assertInstanceOf(stdClass::class, $resolved);
    }

    public function testDefaultParameterValues(): void
    {
        $resolved = $this->container->get(ClassWithDefaultParam::class);

        $this->assertEquals('default', $resolved->value);
    }

    public function testThrowsNotFoundForNonExistent(): void
    {
        $this->assertFalse($this->container->has('non-existent'));

        $this->expectException(NotFoundException::class);
        $this->container->get('non-existent');
    }

    public function testThrowsOnNonInstantiableClass(): void
    {
        $this->expectException(ContainerException::class);
        $this->container->get(AbstractService::class);
    }

    public function testThrowsOnUnresolvableDependency(): void
    {
        $this->expectException(ContainerException::class);
        $this->container->get(ClassWithUnresolvable::class);
    }

    public function testSetInvalidatesCachedInstance(): void
    {
        $this->container->set('conn', fn() => (object)['db' => 'db1']);
        $first = $this->container->get('conn');
        $this->assertSame('db1', $first->db);

        $this->container->set('conn', fn() => (object)['db' => 'db2']);
        $second = $this->container->get('conn');
        $this->assertSame('db2', $second->db);
        $this->assertNotSame($first, $second);
    }

    public function testDetectsSelfBindingCycle(): void
    {
        $this->container->bind(stdClass::class, stdClass::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: stdClass -> stdClass');
        $this->container->get(stdClass::class);
    }

    public function testDetectsTwoStepBindingCycle(): void
    {
        $this->container->bind(stdClass::class, \ArrayObject::class);
        $this->container->bind(\ArrayObject::class, stdClass::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: stdClass -> ArrayObject -> stdClass');
        $this->container->get(stdClass::class);
    }

    public function testDetectsAutoResolveCycle(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches(
            '/Circular dependency detected: \S+CircularA -> \S+CircularB -> \S+CircularA/'
        );
        $this->container->get(CircularA::class);
    }

    public function testDetectsCycleInFactory(): void
    {
        $this->container->set('self', fn($c) => $c->get('self'));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: self -> self');
        $this->container->get('self');
    }

    public function testResetClearsAllInstances(): void
    {
        $this->container->set('a', fn() => new stdClass());
        $this->container->set('b', fn() => new stdClass());
        $a1 = $this->container->get('a');
        $b1 = $this->container->get('b');

        $this->container->reset();

        $this->assertNotSame($a1, $this->container->get('a'));
        $this->assertNotSame($b1, $this->container->get('b'));
    }

    public function testLazyResolvesToUninitializedProxy(): void
    {
        $this->container->lazy(ClassWithDependency::class);

        $proxy = $this->container->get(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $proxy);
        $this->assertLazyUninitialized($proxy);

        // First property access transparently builds the real instance.
        $this->assertInstanceOf(stdClass::class, $proxy->dependency);
    }

    public function testLazyProxyIsSingleton(): void
    {
        $this->container->lazy(ClassWithDependency::class);

        $this->assertSame(
            $this->container->get(ClassWithDependency::class),
            $this->container->get(ClassWithDependency::class)
        );
    }

    public function testLazyBreaksCircularDependency(): void
    {
        $this->container->lazy(CircularB::class);

        $a = $this->container->get(CircularA::class);

        $this->assertInstanceOf(CircularA::class, $a);
        $this->assertInstanceOf(CircularB::class, $a->b);
        // The proxy resolves back to the shared CircularA instance on access.
        $this->assertSame($a, $a->b->a);
        // get() returns the same cached proxy that was injected.
        $this->assertSame($a->b, $this->container->get(CircularB::class));
    }

    public function testLazyOnNonInstantiableClassThrows(): void
    {
        $this->container->lazy(AbstractService::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('is not instantiable');
        $this->container->get(AbstractService::class);
    }

    public function testLazyOnMissingIdThrowsNotFound(): void
    {
        $this->container->lazy('App\\Missing');

        $this->expectException(NotFoundException::class);
        $this->container->get('App\\Missing');
    }

    public function testLazyOnCyclicBindingThrowsInsteadOfHanging(): void
    {
        $this->container->bind(stdClass::class, \ArrayObject::class);
        $this->container->bind(\ArrayObject::class, stdClass::class);
        $this->container->lazy(stdClass::class);

        // The $seen guard in concreteClass() must break the binding cycle and
        // surface a ContainerException rather than looping forever.
        $this->expectException(ContainerException::class);
        $this->container->get(stdClass::class);
    }

    public function testLazyOnUnproxyableInternalClassThrows(): void
    {
        $this->container->lazy(\ArrayObject::class);

        // ArrayObject is instantiable but internal: newLazyProxy() throws a raw
        // \Error that newProxy() must wrap as a ContainerException.
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot create a lazy proxy');
        $this->container->get(\ArrayObject::class);
    }

    public function testLazyAfterEagerResolutionInvalidatesCache(): void
    {
        $real = $this->container->get(ClassWithDependency::class);
        $this->assertNotLazy($real);

        $this->container->lazy(ClassWithDependency::class);

        $proxy = $this->container->get(ClassWithDependency::class);
        $this->assertNotSame($real, $proxy);
        $this->assertLazyUninitialized($proxy);
    }

    public function testLazyResolvesBoundInterfaceToConcreteProxy(): void
    {
        $this->container->bind(ContainerInterface::class, Container::class);
        $this->container->lazy(ContainerInterface::class);

        $proxy = $this->container->get(ContainerInterface::class);

        $this->assertInstanceOf(ContainerInterface::class, $proxy);
        $this->assertInstanceOf(Container::class, $proxy);
        $this->assertLazyUninitialized($proxy);
    }

    public function testNullReturningFactoryIsCached(): void
    {
        $calls = 0;
        $this->container->set('config', function () use (&$calls) {
            $calls++;
            return null;
        });

        $this->assertNull($this->container->get('config'));
        $this->assertNull($this->container->get('config'));
        $this->assertSame(1, $calls);
    }

    public function testLazyAttributeInjectsUninitializedProxy(): void
    {
        $resolved = $this->container->get(NeedsLazyDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $resolved->dependency);
        $this->assertLazyUninitialized($resolved->dependency);

        // First member access transparently builds the real instance.
        $this->assertInstanceOf(stdClass::class, $resolved->dependency->dependency);
    }

    public function testLazyAttributeBreaksCircularDependency(): void
    {
        $a = $this->container->get(AttrCircularA::class);

        $this->assertInstanceOf(AttrCircularA::class, $a);
        $this->assertInstanceOf(AttrCircularB::class, $a->b);
        // The #[Lazy] proxy resolves back to the shared AttrCircularA on access.
        $this->assertSame($a, $a->b->a);
    }
}
