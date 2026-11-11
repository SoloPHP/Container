<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Solo\Container\Attribute\Lazy;
use Solo\Container\Container;
use Solo\Contracts\Container\WritableContainerInterface;

class NeedsLazyContainers
{
    public function __construct(
        #[Lazy] public Container $container,
        #[Lazy] public ContainerInterface $psr,
        #[Lazy] public WritableContainerInterface $writable,
    ) {
    }
}
