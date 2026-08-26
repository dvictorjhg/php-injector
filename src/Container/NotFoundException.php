<?php

declare(strict_types=1);

namespace PHPInjector\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Signals that a requested provider identifier is not registered.
 */
class NotFoundException extends \Exception implements NotFoundExceptionInterface
{
}
