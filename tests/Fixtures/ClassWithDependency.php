<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use stdClass;

class ClassWithDependency
{
    public function __construct(public stdClass $dependency)
    {
    }
}
