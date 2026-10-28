<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class NeedsInterfaceIndirectly
{
    public function __construct(public NeedsInterface $inner)
    {
    }
}
