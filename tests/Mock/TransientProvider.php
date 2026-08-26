<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Mock;

use PHPInjector\DI\Attributes\Transient;

/**
 * Minimal fixture for verifying fresh construction with the Transient attribute.
 */
#[Transient]
class TransientProvider
{
}
