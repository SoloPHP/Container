<?php

declare(strict_types=1);

namespace Solo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
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
use Solo\Tests\Fixtures\ClassWithDependencySubclass;
use Solo\Tests\Fixtures\ClassWithUnresolvable;
use Solo\Tests\Fixtures\NeedsInjectedSelf;
use Solo\Tests\Fixtures\NeedsInjectedServices;
use Solo\Tests\Fixtures\NeedsInjectedValue;
use Solo\Tests\Fixtures\NeedsInjectWithoutId;
use Solo\Tests\Fixtures\NeedsInterface;
use Solo\Tests\Fixtures\NeedsInterfaceIndirectly;
use Solo\Tests\Fixtures\NeedsLazyDependency;
use Solo\Tests\Fixtures\NeedsLazyInjected;
use Solo\Tests\Fixtures\NeedsLazyInjectedContainer;
use Solo\Tests\Fixtures\NeedsOptionalBroken;
use Solo\Tests\Fixtures\NeedsOptionalContainer;
use Solo\Tests\Fixtures\NeedsOptionalInjected;
use Solo\Tests\Fixtures\Stateless;
use ArrayObject;
use ReflectionClass;
use stdClass;
use TypeError;

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
        $this->container->set(stdClass::class, fn() => (object)['db' => 'db1']);
        $first = $this->container->get(stdClass::class);
        $this->assertSame('db1', $first->db);

        $this->container->set(stdClass::class, fn() => (object)['db' => 'db2']);
        $second = $this->container->get(stdClass::class);
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
        $this->container->bind(stdClass::class, ArrayObject::class);
        $this->container->bind(ArrayObject::class, stdClass::class);

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
        $this->container->bind(stdClass::class, ArrayObject::class);
        $this->container->bind(ArrayObject::class, stdClass::class);
        $this->container->lazy(stdClass::class);

        // The $seen guard in concreteClass() must break the binding cycle and
        // surface a ContainerException rather than looping forever.
        $this->expectException(ContainerException::class);
        $this->container->get(stdClass::class);
    }

    public function testLazyOnUnproxyableInternalClassThrows(): void
    {
        $this->container->lazy(ArrayObject::class);

        // ArrayObject is instantiable but internal: newLazyProxy() throws a raw
        // \Error that newProxy() must wrap as a ContainerException.
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot create a lazy proxy');
        $this->container->get(ArrayObject::class);
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

    public function testInjectResolvesParametersById(): void
    {
        $primary = new stdClass();
        $secondary = new stdClass();
        $this->container->set('primary', fn() => $primary);
        $this->container->set('secondary', fn() => $secondary);

        $resolved = $this->container->get(NeedsInjectedServices::class);

        $this->assertSame($primary, $resolved->primary);
        $this->assertSame($secondary, $resolved->secondary);
    }

    public function testInjectPassesNonClassValuesAsTheyAre(): void
    {
        $this->container->set('app.name', fn() => 'Solo');

        $this->assertSame('Solo', $this->container->get(NeedsInjectedValue::class)->name);
    }

    public function testInjectOnMissingIdThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            'Cannot resolve ' . NeedsInjectedServices::class . ": Service 'primary' not found in container."
        );

        $this->container->get(NeedsInjectedServices::class);
    }

    public function testInjectOnMissingIdFallsBackToTheDefault(): void
    {
        $this->assertSame('default', $this->container->get(NeedsInjectedValue::class)->name);
    }

    #[DataProvider('notAStdClass')]
    public function testInjectRejectsAValueTheTypeDoesNotAccept(mixed $value): void
    {
        $this->container->set('primary', fn() => $value);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Service 'primary' injected into '\$primary' in class '" . NeedsInjectedServices::class
            . "' is " . get_debug_type($value) . ', not stdClass.'
        );

        $this->container->get(NeedsInjectedServices::class);
    }

    /** @return iterable<string, array{mixed}> */
    public static function notAStdClass(): iterable
    {
        yield 'another class' => [new ArrayObject()];
        yield 'null for a non-nullable parameter' => [null];
    }

    public function testInjectPassesNullToNullableParameter(): void
    {
        $this->container->set('optional', fn() => null);

        $this->assertNull($this->container->get(NeedsOptionalInjected::class)->optional);
    }

    public function testInjectWithLazyResolvesTheIdOnFirstUse(): void
    {
        $calls = 0;
        $this->container->set('dependency', function () use (&$calls) {
            $calls++;
            return new ClassWithDependency(new stdClass());
        });

        $resolved = $this->container->get(NeedsLazyInjected::class);

        $this->assertLazyUninitialized($resolved->dependency);
        $this->assertSame(0, $calls);
        $this->assertInstanceOf(stdClass::class, $resolved->dependency->dependency);
        $this->assertSame(1, $calls);
    }

    #[DataProvider('notExactlyTheProxiedClass')]
    public function testInjectWithLazyChecksTheExactClassOnFirstUse(mixed $value): void
    {
        $this->container->set('dependency', fn() => $value);
        $resolved = $this->container->get(NeedsLazyInjected::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            'it resolved to ' . get_debug_type($value)
            . ', not an instance of exactly ' . ClassWithDependency::class . '.'
        );

        $this->assertInstanceOf(stdClass::class, $resolved->dependency->dependency);
    }

    /** @return iterable<string, array{mixed}> */
    public static function notExactlyTheProxiedClass(): iterable
    {
        yield 'another class' => [new stdClass()];
        yield 'a subclass' => [new ClassWithDependencySubclass(new stdClass())];
        yield 'null' => [null];
    }

    public function testInjectWithLazyProxiesAnInterfaceThroughItsBinding(): void
    {
        $inner = new Container();
        $inner->set('marker', fn() => 'inner');
        $this->container->set('container', fn() => $inner);
        $this->container->bind(ContainerInterface::class, Container::class);

        $resolved = $this->container->get(NeedsLazyInjectedContainer::class);

        $this->assertLazyUninitialized($resolved->container);
        $this->assertSame('inner', $resolved->container->get('marker'));
    }

    public function testInjectWithLazyOnUnboundInterfaceThrowsContainerException(): void
    {
        $this->container->set('container', fn() => new Container());

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "'" . ContainerInterface::class . "' does not resolve to an instantiable class."
        );

        $this->container->get(NeedsLazyInjectedContainer::class);
    }

    public function testLazyInjectionForwardsToTheInstanceBehindALazyService(): void
    {
        $this->container->lazy(ClassWithDependency::class);
        $this->container->bind('dependency', ClassWithDependency::class);

        $direct = $this->container->get(NeedsLazyDependency::class)->dependency;
        $injected = $this->container->get(NeedsLazyInjected::class)->dependency;

        // Both proxies and the service's own proxy share one real instance.
        $marker = new stdClass();
        $direct->dependency = $marker;
        $this->assertSame($marker, $injected->dependency);
        $this->assertSame($marker, $this->container->get(ClassWithDependency::class)->dependency);
    }

    public function testInjectResolvesSelfTypedParameter(): void
    {
        $peer = new NeedsInjectedSelf(null);
        $this->container->set('peer', fn() => $peer);

        $this->assertSame($peer, $this->container->get(NeedsInjectedSelf::class)->peer);
    }

    public function testInjectWithoutIdThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Invalid #[Inject] on '\$service' in class '" . NeedsInjectWithoutId::class . "'"
        );

        $this->container->get(NeedsInjectWithoutId::class);
    }

    /** @param callable(Container): void $register */
    #[DataProvider('notAContainer')]
    public function testAnInterfaceIdMustResolveToAnInstanceOfIt(callable $register, string $actual): void
    {
        $register($this->container);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Service '" . ContainerInterface::class . "' is $actual, not an instance of "
            . ContainerInterface::class . '.'
        );

        $this->container->get(ContainerInterface::class);
    }

    /** @return iterable<string, array{callable(Container): void, string}> */
    public static function notAContainer(): iterable
    {
        $factory = static fn(mixed $value) => static fn(Container $c) => $c->set(
            ContainerInterface::class,
            fn() => $value,
        );

        yield 'a factory returning another class' => [$factory(new stdClass()), 'stdClass'];
        yield 'a factory returning null' => [$factory(null), 'null'];
        yield 'a factory returning a string' => [$factory('container'), 'string'];
        yield 'a binding to another class' => [
            static fn(Container $c) => $c->bind(ContainerInterface::class, stdClass::class),
            'stdClass',
        ];
        yield 'a lazy binding to another class' => [
            static function (Container $c): void {
                $c->bind(ContainerInterface::class, ClassWithDependency::class);
                $c->lazy(ContainerInterface::class);
            },
            ClassWithDependency::class,
        ];
    }

    public function testFactoryUnderAClassNameMayReturnASubclass(): void
    {
        $subclass = new ClassWithDependencySubclass(new stdClass());
        $this->container->set(ClassWithDependency::class, fn() => $subclass);

        $this->assertSame($subclass, $this->container->get(ClassWithDependency::class));
    }

    public function testBindInvalidatesCachedInstance(): void
    {
        $first = $this->container->get(ClassWithDependency::class);

        $this->container->bind(ClassWithDependency::class, ClassWithDependencySubclass::class);
        $second = $this->container->get(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependencySubclass::class, $second);
        $this->assertNotSame($first, $second);
    }

    public function testTheLastRegistrationOfAnIdWins(): void
    {
        $this->container->set('service', fn() => 'factory');
        $this->container->bind('service', stdClass::class);
        $this->assertInstanceOf(stdClass::class, $this->container->get('service'));

        $this->container->set('service', fn() => 'factory');
        $this->assertSame('factory', $this->container->get('service'));
    }

    public function testMissingDependencyIsAContainerExceptionNamingTheChain(): void
    {
        try {
            $this->container->get(NeedsInterfaceIndirectly::class);
            $this->fail('Expected a ContainerException.');
        } catch (ContainerException $e) {
            $this->assertSame(
                'Cannot resolve ' . NeedsInterfaceIndirectly::class . ' -> ' . NeedsInterface::class
                . ": Service '" . ContainerInterface::class . "' not found in container.",
                $e->getMessage()
            );
            $this->assertInstanceOf(NotFoundException::class, $e->getPrevious());
        }
    }

    public function testFactoryThatGetsAMissingIdThrowsContainerException(): void
    {
        $this->container->set('report', fn(Container $c) => $c->get('missing'));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Cannot resolve report: Service 'missing' not found in container.");

        $this->container->get('report');
    }

    public function testFactoryThatHitsAForeignNotFoundThrowsContainerException(): void
    {
        $missing = new class ('Entry not found.') extends \Exception implements NotFoundExceptionInterface {
        };
        $this->container->set('report', fn() => throw $missing);

        try {
            $this->container->get('report');
            $this->fail('Expected a ContainerException.');
        } catch (ContainerException $e) {
            $this->assertSame('Cannot resolve report: Entry not found.', $e->getMessage());
            $this->assertSame($missing, $e->getPrevious());
        }
    }

    public function testBindingToAMissingClassThrowsContainerException(): void
    {
        // @phpstan-ignore argument.type (a binding to a class that does not exist)
        $this->container->bind('logger', 'App\\MissingLogger');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Cannot resolve logger: Service 'App\\MissingLogger' not found in container.");

        $this->container->get('logger');
    }

    public function testUnknownOptionalDependencyTakesTheDefault(): void
    {
        $resolved = $this->container->get(NeedsOptionalContainer::class);

        $this->assertNull($resolved->nullable);
        $this->assertInstanceOf(Container::class, $resolved->withDefault);
        $this->assertNotSame($this->container, $resolved->withDefault);
    }

    public function testDefaultObjectIsBuiltAnewForEachInstance(): void
    {
        $first = $this->container->get(NeedsOptionalContainer::class)->withDefault;
        $this->container->reset();

        $this->assertNotSame($first, $this->container->get(NeedsOptionalContainer::class)->withDefault);
    }

    public function testInvalidInjectFailsBeforeAnyDependencyIsBuilt(): void
    {
        $calls = 0;
        $this->container->set(ClassWithDependency::class, function () use (&$calls) {
            $calls++;

            return new ClassWithDependency(new stdClass());
        });

        try {
            $this->container->get(NeedsInjectWithoutId::class);
            $this->fail('Expected a ContainerException.');
        } catch (ContainerException $e) {
            $this->assertStringStartsWith("Invalid #[Inject] on '\$service'", $e->getMessage());
            $this->assertSame(0, $calls);
        }
    }

    public function testKnownOptionalDependencyIsResolved(): void
    {
        $this->container->set(ContainerInterface::class, fn(Container $c) => $c);

        $resolved = $this->container->get(NeedsOptionalContainer::class);

        $this->assertSame($this->container, $resolved->nullable);
        $this->assertSame($this->container, $resolved->withDefault);
    }

    public function testOptionalDependencyThatCannotBeBuiltDoesNotFallBack(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve ' . NeedsOptionalBroken::class . ' -> ' . NeedsInterface::class);

        $this->container->get(NeedsOptionalBroken::class);
    }

    public function testLazyInjectionOfAnUnknownIdFailsAtInjection(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            'Cannot resolve ' . NeedsLazyInjected::class . ": Service 'dependency' not found in container."
        );

        $this->container->get(NeedsLazyInjected::class);
    }

    public function testLazyClassWithoutPropertiesIsBuiltAtOnce(): void
    {
        $calls = 0;
        $this->container->set(Stateless::class, function () use (&$calls) {
            $calls++;

            return new Stateless();
        });
        $this->container->lazy(Stateless::class);

        $service = $this->container->get(Stateless::class);

        // PHP cannot defer a class without properties, so the factory runs now rather than never.
        $this->assertSame(1, $calls);
        $this->assertInstanceOf(Stateless::class, $service);
        $this->assertNotLazy($service);
    }

    public function testLazyFactoryReturningASubclassFailsOnFirstUse(): void
    {
        $this->container->set(ClassWithDependency::class, fn() => new ClassWithDependencySubclass(new stdClass()));
        $this->container->lazy(ClassWithDependency::class);
        $proxy = $this->container->get(ClassWithDependency::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Cannot initialize the lazy proxy for '" . ClassWithDependency::class . "': it resolved to "
            . ClassWithDependencySubclass::class
        );

        $this->assertInstanceOf(stdClass::class, $proxy->dependency);
    }

    public function testConstructorRegistersFactoriesThroughSet(): void
    {
        // @phpstan-ignore argument.type (PHP turns the numeric-string key into an int)
        $container = new Container(['1' => fn() => 'one']);

        $this->assertSame('one', $container->get('1'));

        $this->expectException(TypeError::class);
        // @phpstan-ignore argument.type (not a callable)
        new Container(['bad' => 'not a callable']);
    }
}
