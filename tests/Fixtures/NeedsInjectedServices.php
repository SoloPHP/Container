<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

use Solo\Container\Attribute\Inject;
use stdClass;

class NeedsInjectedServices
{
    public function __construct(
        #[Inject('primary')] public stdClass $primary,
        #[Inject('secondary')] public stdClass $secondary,
    ) {
    }
}
