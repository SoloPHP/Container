<?php

declare(strict_types=1);

namespace Solo\Tests;

class ClassWithUnresolvableDependency
{
    public function __construct(
        public string $unresolvable
    ) {
    }
}
