<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class AttrCircularB
{
    public function __construct(public AttrCircularA $a)
    {
    }
}
