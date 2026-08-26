<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Mock;

use PHPInjector\Contracts\Singleton;
use PHPInjector\DI\Attributes\Transient;

/**
 * Fixture combining transient injector caching with a self-managed getInstance() implementation.
 * The class implementation owns object identity; the attribute only controls injector caching.
 */
#[Transient]
class TransientSingletonProvider implements Singleton
{
    private static ?self $instance = null;

    private function __construct()
    {
        // Construction is intentionally private; getInstance() controls instance creation.
    }

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
