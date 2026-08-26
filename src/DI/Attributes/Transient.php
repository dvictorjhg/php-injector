<?php

declare(strict_types=1);

namespace PHPInjector\DI\Attributes;

use Attribute;

/**
 * Opt a class out of injector-managed singleton caching.
 *
 * This marker affects automatically constructed classes. It does not replace
 * an explicitly supplied object or override a class's Singleton implementation.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Transient
{
}
