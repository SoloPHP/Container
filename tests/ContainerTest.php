<?php

declare(strict_types=1);

namespace Solo\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Solo\Container\Container;
use Solo\Container\Exceptions\ContainerException;
use Solo\Container\Exceptions\NotFoundException;
use Solo\Tests\Fixtures\AbstractService;
use Solo\Tests\Fixtures\ClassWithDefaultParam;
use Solo\Tests\Fixtures\ClassWithDependency;
use Solo\Tests\Fixtures\ClassWithUnresolvable;
use stdClass;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
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
}
