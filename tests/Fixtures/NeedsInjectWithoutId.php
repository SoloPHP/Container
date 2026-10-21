<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Inject;
use stdClass;

class NeedsInjectWithoutId
{
    // Deliberately malformed: #[Inject] requires an id.
    // @phpstan-ignore arguments.count
    public function __construct(#[Inject] public stdClass $service)
    {
    }
}
