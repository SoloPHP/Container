<?php

declare(strict_types=1);

namespace Solo\Tests;

use PHPUnit\Framework\TestCase;
use Solo\Container\Container;
use Solo\Container\Exceptions\ContainerException;
use Solo\Container\Exceptions\NotFoundException;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testContainerImplementsPsr11Interface(): void
    {
        $this->assertInstanceOf(\Psr\Container\ContainerInterface::class, $this->container);
    }

    public function testSetAndGetService(): void
    {
        $this->container->set('test', fn() => 'test-value');

        $this->assertEquals('test-value', $this->container->get('test'));
    }

    public function testSetMultipleServices(): void
    {
        $services = [
            'service1' => fn() => 'value1',
            'service2' => fn() => 'value2',
        ];

        $container = new Container($services);

        $this->assertEquals('value1', $container->get('service1'));
        $this->assertEquals('value2', $container->get('service2'));
    }

    public function testSetMultipleServicesWithInvalidCallable(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Service [invalid] must be a callable.');

        new Container(['invalid' => 'not-callable']);
    }

    public function testServiceFactoryReceivesContainer(): void
    {
        $this->container->set('test', function ($container) {
            $this->assertSame($this->container, $container);
            return 'test-value';
        });

        $this->container->get('test');
    }

    public function testBindInterfaceToConcrete(): void
    {
        $this->container->bind(TestInterface::class, TestImplementation::class);

        $instance = $this->container->get(TestInterface::class);
        $this->assertInstanceOf(TestImplementation::class, $instance);
    }

    public function testAutoResolveClassWithoutDependencies(): void
    {
        $instance = $this->container->get(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testAutoResolveClassWithDependencies(): void
    {
        $this->container->set(TestInterface::class, fn() => new TestImplementation());

        $instance = $this->container->get(ClassWithDependency::class);
        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(TestImplementation::class, $instance->dependency);
    }

    public function testAutoResolveClassWithMultipleDependencies(): void
    {
        $this->container->set(TestInterface::class, fn() => new TestImplementation());
        $this->container->set(ConfigClass::class, fn() => new ConfigClass(['host' => 'localhost']));

        $instance = $this->container->get(ClassWithMultipleDependencies::class);
        $this->assertInstanceOf(ClassWithMultipleDependencies::class, $instance);
    }

    public function testSingletonBehavior(): void
    {
        $this->container->set('singleton', fn() => new \stdClass());

        $instance1 = $this->container->get('singleton');
        $instance2 = $this->container->get('singleton');

        $this->assertSame($instance1, $instance2);
    }

    public function testHasReturnsTrueForRegisteredService(): void
    {
        $this->container->set('test', fn() => 'value');

        $this->assertTrue($this->container->has('test'));
    }

    public function testHasReturnsTrueForBoundInterface(): void
    {
        $this->container->bind(TestInterface::class, TestImplementation::class);

        $this->assertTrue($this->container->has(TestInterface::class));
    }

    public function testHasReturnsTrueForExistingClass(): void
    {
        $this->assertTrue($this->container->has(SimpleClass::class));
    }

    public function testHasReturnsFalseForNonExistentService(): void
    {
        $this->assertFalse($this->container->has('non-existent'));
    }

    public function testGetThrowsNotFoundExceptionForNonExistentService(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Service [non-existent] not found in the container.');

        $this->container->get('non-existent');
    }

    public function testGetThrowsContainerExceptionForNonInstantiableClass(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Class [Solo\Tests\AbstractClass] is not instantiable.');

        $this->container->get(AbstractClass::class);
    }

    public function testGetThrowsContainerExceptionForUnresolvableDependency(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve dependency [unresolvable] for parameter');

        $this->container->get(ClassWithUnresolvableDependency::class);
    }

    public function testResolveClassWithDefaultParameterValues(): void
    {
        $instance = $this->container->get(ClassWithDefaultParameters::class);
        $this->assertInstanceOf(ClassWithDefaultParameters::class, $instance);
        $this->assertEquals('default', $instance->param);
    }
}
