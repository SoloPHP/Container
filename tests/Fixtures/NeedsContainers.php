<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Solo\Container\Container;
use Solo\Contracts\Container\WritableContainerInterface;

class NeedsContainers
{
    public function __construct(
        public Container $container,
        public ContainerInterface $psr,
        public WritableContainerInterface $writable,
    ) {
    }
}
