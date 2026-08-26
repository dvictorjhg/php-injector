<?php

declare(strict_types=1);

namespace PHPInjector\Exceptions;

use Exception;

/**
 * Signals that a target or one of its parameters cannot be injected.
 */
class InjectorException extends Exception
{
}
