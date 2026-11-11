<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Solo\Container\Attribute\Inject;

class NeedsInjectedContainer
{
    public function __construct(#[Inject('container')] public ContainerInterface $container)
    {
    }
}
