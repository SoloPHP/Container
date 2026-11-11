<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Inject;
use Solo\Container\Attribute\Lazy;

class NeedsLazyInjectedService
{
    public function __construct(#[Inject('service')] #[Lazy] public ServiceInterface $service)
    {
    }
}
