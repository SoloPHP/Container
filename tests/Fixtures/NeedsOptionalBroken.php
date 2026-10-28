<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class NeedsOptionalBroken
{
    public function __construct(public ?NeedsInterface $inner = null)
    {
    }
}
