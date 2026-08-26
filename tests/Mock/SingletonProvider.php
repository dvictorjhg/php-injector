<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Mock;

use PHPInjector\Contracts\Singleton;

/**
 * Singleton test double with mutable state so tests can observe instance reuse.
 */
class SingletonProvider implements Singleton
{
    private static ?self $instance = null;
    public const string A = 'A';
    public string $instanceValue;

    private function __construct()
    {
        $this->instanceValue = 'B';
    }

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
