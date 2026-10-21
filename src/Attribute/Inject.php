<?php

declare(strict_types=1);

namespace Solo\Container\Attribute;

use Attribute;

/**
 * Resolve a constructor parameter from a service id instead of its type — for dependencies
 * the type cannot tell apart, such as several instances of one class.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(public string $id)
    {
    }
}
