<?php

declare(strict_types=1);

namespace PHPInjector\Container;

use Psr\Container\ContainerExceptionInterface;

/**
 * Signals invalid container data or another provider-storage failure.
 */
class ContainerException extends \Exception implements ContainerExceptionInterface
{
}
