<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Mock;

/**
 * Provider with its own dependency so tests can expose recursive resolution.
 */
class ProviderWithRequiredProvider extends SimpleProvider
{
    public SimpleProvider $requiredProvider;

    public function __construct(SimpleProvider $simpleProvider)
    {
        $this->requiredProvider = $simpleProvider;
    }
}
