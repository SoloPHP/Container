<?php

declare(strict_types=1);

namespace Solo\Tests;

class ClassWithMultipleDependencies
{
    public function __construct(
        public ConfigClass $config,
        public TestInterface $logger
    ) {
    }
}
