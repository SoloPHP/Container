<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Inject;
use stdClass;

class NeedsOptionalInjected
{
    public function __construct(#[Inject('optional')] public ?stdClass $optional)
    {
    }
}
