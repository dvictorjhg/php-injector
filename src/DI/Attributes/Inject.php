<?php

declare(strict_types=1);

namespace PHPInjector\DI\Attributes;

use Attribute;

/**
 * Bind one parameter to a provider identifier in the active injector.
 *
 * Explicit per-call arguments still take precedence over this attribute.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(public string $id)
    {
    }
}
