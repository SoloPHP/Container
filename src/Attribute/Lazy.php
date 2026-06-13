<?php

declare(strict_types=1);

namespace Solo\Container\Attribute;

use Attribute;

/**
 * Marks a constructor parameter so the container injects a lazy proxy of the
 * shared service instead of resolving it eagerly. Use it to break a circular
 * dependency at a specific injection point, or to defer an expensive one.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Lazy
{
}
