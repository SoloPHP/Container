<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Inject;

class NeedsInjectedValue
{
    public function __construct(#[Inject('app.name')] public string $name = 'default')
    {
    }
}
