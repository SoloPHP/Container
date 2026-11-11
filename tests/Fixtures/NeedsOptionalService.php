<?php

declare(strict_types=1);

namespace Solo\Tests\Fixtures;

class NeedsOptionalService
{
    public function __construct(
        public ?ServiceInterface $nullable = null,
        public ServiceInterface $withDefault = new DefaultService(),
    ) {
    }
}
