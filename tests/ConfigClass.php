<?php

declare(strict_types=1);

namespace Solo\Tests;

class ConfigClass
{
    public function __construct(
        /** @var array<string, mixed> */
        public array $data
    ) {
    }
}
