<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Mock;

use Stringable;
use function array_map;
use function get_debug_type;
use function implode;
use function is_bool;
use function is_scalar;

/**
 * Reports whether a dynamic call was resolved in object or static context.
 *
 * @method string runTest(string ...$arguments)
 * @method static string runTest(string ...$arguments)
 */
class MagicMethods
{
    /**
     * @param array<int, mixed>|null $arguments
     */
    public function __call(string $name, ?array $arguments): string
    {
        return "Calling object method '$name'"
            . ($arguments ? ' ' . implode(', ', array_map(static fn (mixed $argument): string => self::stringifyArgument($argument), $arguments)) : '');
    }

    /**
     * @param array<int, mixed>|null $arguments
     */
    public static function __callStatic(string $name, ?array $arguments): string
    {
        return "Calling static method '$name'"
            . ($arguments ? ' ' . implode(', ', array_map(static fn (mixed $argument): string => self::stringifyArgument($argument), $arguments)) : '');
    }

    private static function stringifyArgument(mixed $argument): string
    {
        $stringifiedArgument = get_debug_type($argument);

        if ($argument === null) {
            $stringifiedArgument = 'null';
        } elseif (is_bool($argument)) {
            $stringifiedArgument = $argument ? 'true' : 'false';
        } elseif (is_scalar($argument) || $argument instanceof Stringable) {
            $stringifiedArgument = (string) $argument;
        }

        return $stringifiedArgument;
    }
}
