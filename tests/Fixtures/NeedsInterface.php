<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class NeedsInterface
{
    public function __construct(public ServiceInterface $service)
    {
    }
}
