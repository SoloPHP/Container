<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}
