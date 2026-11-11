<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class NeedsParent extends ClassWithDependency
{
    public function __construct(public parent $base)
    {
        parent::__construct($base->dependency);
    }
}
