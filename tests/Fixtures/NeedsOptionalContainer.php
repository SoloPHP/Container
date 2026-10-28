<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Solo\Container\Container;

class NeedsOptionalContainer
{
    public function __construct(
        public ?ContainerInterface $nullable = null,
        public ContainerInterface $withDefault = new Container(),
    ) {
    }
}
