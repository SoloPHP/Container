<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class ClassWithDefaultParam
{
    public function __construct(public string $value = 'default')
    {
    }
}
