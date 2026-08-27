<?php

declare(strict_types=1);

namespace PHPInjector\DI\Attributes;

use Attribute;

/**
 * Bind one parameter to a provider identifier in the active injector after
 * explicit per-call arguments have failed to resolve it.
 *
 * Provider lookup continues through the active injector's parent chain.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(public string $id)
    {
    }
}
