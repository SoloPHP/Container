<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Psr\Container\ContainerInterface;

class NeedsInterface
{
    public function __construct(public ContainerInterface $container)
    {
    }
}
