<?php

declare(strict_types=1);

namespace Solo\Tests;

class ClassWithDefaultParameters
{
    public function __construct(
        public string $param = 'default'
    ) {
    }
}
