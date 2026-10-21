<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Inject;
use Solo\Container\Attribute\Lazy;

class NeedsLazyInjected
{
    public function __construct(#[Inject('dependency')] #[Lazy] public ClassWithDependency $dependency)
    {
    }
}
