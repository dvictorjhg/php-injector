<?php

declare(strict_types=1);

namespace PHPInjector\Contracts;

/**
 * Marks a class whose own factory controls instance creation.
 *
 * The injector calls getInstance() instead of the constructor when resolving
 * a class that implements this contract. The implementation, rather than the
 * injector, is responsible for returning the same object when that is required.
 */
interface Singleton
{
    public static function getInstance(): self;
}
