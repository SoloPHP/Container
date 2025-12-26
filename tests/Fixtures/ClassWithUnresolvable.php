<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class ClassWithUnresolvable
{
    public function __construct(public string $required)
    {
    }
}
