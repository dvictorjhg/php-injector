<?php

declare(strict_types=1);

namespace PHPInjector\DI;

/**
 * Inject a class, method, function, closure, or invokable object through the active injector.
 *
 * The most recently constructed injector is the active injector. When a new injector is
 * constructed without an explicit parent, it becomes a child of the previous active injector.
 *
 * @param array<int|string, mixed>|callable|class-string|object|string $injectionTarget
 * @param array<int|string, mixed> $args
 * @throws \PHPInjector\Exceptions\InjectorException When the target cannot be resolved.
 */
function inject(array|callable|object|string $injectionTarget, array $args = []): mixed
{
    return Injector::inject($injectionTarget, $args);
}
