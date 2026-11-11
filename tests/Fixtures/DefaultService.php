<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class DefaultService implements ServiceInterface
{
    public function __construct(public string $name = 'default')
    {
    }
}
