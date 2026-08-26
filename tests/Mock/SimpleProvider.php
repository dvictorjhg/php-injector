<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Mock;

/**
 * Provider that records where it came from so tests can show precedence rules.
 */
class SimpleProvider extends Provider
{
    public function __construct(public ?string $providedIn = null)
    {
    }
}
