<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Mock;

/**
 * Consumer used to show that singleton dependencies stay shared after injection.
 */
class SingletonConsumer
{
    public function __construct(public SingletonProvider $singletonProvider)
    {
    }
}
