<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Lazy;

class AttrCircularA
{
    public function __construct(#[Lazy] public AttrCircularB $b)
    {
    }
}
