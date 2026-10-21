<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Solo\Container\Attribute\Inject;
use Solo\Container\Attribute\Lazy;

class NeedsLazyInjectedContainer
{
    public function __construct(#[Inject('container')] #[Lazy] public ContainerInterface $container)
    {
    }
}
