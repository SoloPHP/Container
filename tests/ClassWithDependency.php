<?php

declare(strict_types=1);

namespace Solo\Tests;

class ClassWithDependency
{
    public function __construct(
        public TestInterface $dependency
    ) {
    }
}
