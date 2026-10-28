<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Inject;
use stdClass;

class NeedsInjectWithoutId
{
    public function __construct(
        public ClassWithDependency $first,
        // Deliberately malformed: #[Inject] requires an id.
        // @phpstan-ignore arguments.count
        #[Inject] public stdClass $service,
    ) {
    }
}
