<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Inject;

class NeedsInjectedSelf
{
    public function __construct(#[Inject('peer')] public ?self $peer)
    {
    }
}
