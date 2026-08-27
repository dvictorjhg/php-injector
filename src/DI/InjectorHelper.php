<?php

declare(strict_types=1);

namespace PHPInjector\DI;

use Closure;
use function array_key_exists;
use function class_exists;
use function count;
use function explode;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function strpos;
use PHPInjector\DI\Attributes\Inject;
use PHPInjector\DI\Attributes\Transient;
use PHPInjector\Exceptions\InjectorException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Shared validation, routing, and reflection helpers for Injector.
 *
 * Keeping these decisions here gives the injector one implementation of
 * callable routing, argument precedence, and reflection edge cases.
 */
final class InjectorHelper
{
    /**
     * Validate the target shape and reject ambiguous argument arrays.
     *
    * @param array<int|string, mixed>|callable|class-string|object|string $injectionTarget
     * @param array<int|string, mixed> $args
     */
    public static function assertValidInjectionInput(array|callable|object|string $injectionTarget, array $args): void
    {
        if (empty($injectionTarget)) {
            throw new InjectorException('Non-injectable injection target provided.');
        }

        if (!empty($args) && self::arrayHasMixedKeys($args)) {
            throw new InjectorException('The provided $args array contains mixed keys.');
        }
    }

    /**
     * @param array<int|string, mixed>|mixed $injectionTarget
     * @phpstan-assert-if-true array{0: class-string|object, 1: string} $injectionTarget
     *
     * A valid method target contains exactly an object or existing class name
     * followed by a string method name.
     */
    public static function isMethodTarget(mixed $injectionTarget): bool
    {
        if (!is_array($injectionTarget) || count($injectionTarget) !== 2) {
            return false;
        }

        $target = $injectionTarget[0] ?? null;
        $method = $injectionTarget[1] ?? null;

        return (is_object($target) || (is_string($target) && class_exists($target)))
            && is_string($method);
    }

    /**
     * Route each supported callable shape to the matching injection path.
     *
     * @param array<int|string, mixed> $args
     * @param Closure(object|string, array<int|string, mixed>, string|null): mixed $injectObject
     * @param Closure(Closure|string, array<int|string, mixed>): mixed $injectFunction
     */
    public static function injectCallable(
        callable $callable,
        array $args,
        Closure $injectObject,
        Closure $injectFunction
    ): mixed {
        if (is_string($callable)) {
            if (strpos($callable, '::') !== false) {
                /** @var class-string $class */
                [$class, $method] = explode('::', $callable, 2);

                $result = $injectObject($class, $args, $method);
            } else {
                $result = $injectFunction($callable, $args);
            }
        } elseif (is_array($callable)) {
            /** @var class-string|object $object */
            /** @var non-falsy-string $method */
            [$object, $method] = $callable;

            $result = $injectObject($object, $args, $method);
        } elseif ($callable instanceof Closure) {
            $result = $injectFunction($callable, $args);
        } else {
            /** @var callable&object $callable */
            $result = $injectObject($callable, $args, '__invoke');
        }

        return $result;
    }

    /**
     * Invoke a function or closure after resolving its parameters.
     *
     * @param array<int|string, mixed> $args
     * @param Closure(ReflectionMethod|ReflectionFunction, array<int|string, mixed>): array<int|string, mixed> $resolveParameters
     * @throws InjectorException When a named function cannot be reflected.
     */
    public static function injectFunction(Closure|string $function, array $args, Closure $resolveParameters): mixed
    {
        try {
            $reflectionFunction = new ReflectionFunction($function);
        } catch (ReflectionException $e) {
            throw new InjectorException('Cannot create a ReflectionFunction: ' . $e->getMessage());
        }

        return $reflectionFunction->invokeArgs($resolveParameters($reflectionFunction, $args));
    }

    /**
     * Construct a reflected class, resolving constructor parameters when needed.
     *
     * @param array<int|string, mixed> $args
     * @template TObject of object
     * @param ReflectionClass<TObject> $reflectionClass
     * @param Closure(ReflectionMethod|ReflectionFunction, array<int|string, mixed>): array<int|string, mixed> $resolveParameters
     * @return TObject
     */
    public static function instantiateClass(ReflectionClass $reflectionClass, array $args, Closure $resolveParameters): object
    {
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return $reflectionClass->newInstance();
        }

        return $reflectionClass->newInstanceArgs($resolveParameters($constructor, $args));
    }

    /**
     * @template TObject of object
     * @param ReflectionClass<TObject> $reflectionClass
     */
    public static function resolveMagicMethodName(ReflectionClass $reflectionClass, object|string $id): ?string
    {
        $preferredMethods = is_string($id)
            ? ['__callStatic', '__call']
            : ['__call', '__callStatic'];

        foreach ($preferredMethods as $magicMethod) {
            if ($reflectionClass->hasMethod($magicMethod)) {
                return $magicMethod;
            }
        }

        return null;
    }

    /**
     * Reject a reflected method that cannot be called by the injector.
     */
    public static function assertPublicMethod(
        ReflectionMethod $reflectionMethod,
        string $className,
        string $methodName,
        bool $isMagicMethod = false
    ): void {
        if ($reflectionMethod->isPublic()) {
            return;
        }

        $methodType = $isMagicMethod ? 'Magic method' : 'Method';

        throw new InjectorException(
            "$methodType '$methodName' of class '$className' is not accessible."
        );
    }

    /**
     * Return the injectable name for a non-builtin named type.
     */
    public static function resolveParameterTypeName(\ReflectionType|null $parameterType): string
    {
        if (
            !$parameterType instanceof ReflectionNamedType
            || $parameterType->isBuiltin()
        ) {
            return '';
        }

        return $parameterType->getName();
    }

    /**
     * Build the exception message for an unresolved required parameter.
     */
    public static function buildMissingParameterMessage(\ReflectionParameter $reflectionParameter, string $parameterTypeName): string
    {
        return 'No provider or value found for parameter: '
            . ($parameterTypeName !== '' ? "$parameterTypeName " : '')
            . "\${$reflectionParameter->name} at position {$reflectionParameter->getPosition()}.";
    }

    /**
     * Resolve the provider named by the first Inject attribute, if present.
     * The caller invokes this after explicit arguments have no match.
     */
    public static function resolveProviderFromAttribute(
        \ReflectionParameter $reflectionParameter,
        Closure $getProvider,
        mixed $missingValue
    ): mixed {
        $injectAttributes = $reflectionParameter->getAttributes(Inject::class);
        $injectAttribute = $injectAttributes[0] ?? null;

        if ($injectAttribute === null) {
            return $missingValue;
        }

        return $getProvider($injectAttribute->newInstance()->id);
    }

    /**
     * Resolve a provider for a supported non-builtin named parameter type.
     * The caller invokes this after explicit and attribute lookup have no match.
     */
    public static function resolveProviderFromType(
        \ReflectionType|null $parameterType,
        Closure $getProvider,
        mixed $missingValue
    ): mixed {
        if (
            !$parameterType instanceof ReflectionNamedType
            || $parameterType->isBuiltin()
        ) {
            return $missingValue;
        }

        return $getProvider($parameterType->getName(), $parameterType);
    }

    /**
     * Find an explicit argument by type key, parameter name, then position.
     * Type-key lookup is available only for a non-builtin named parameter type.
     * For variadics, collect every numeric value from the parameter position
     * onward instead of resolving a single positional value.
     *
     * @param array<int|string, mixed> $args
     */
    public static function getArgumentValue(
        array $args,
        string $parameterTypeName,
        string $parameterName,
        int $parameterPosition,
        mixed $missingValue,
        bool $variadic = false
    ): mixed {
        $resolvedValue = $missingValue;

        if ($parameterTypeName !== '' && array_key_exists($parameterTypeName, $args)) {
            $resolvedValue = $args[$parameterTypeName];
        } elseif (array_key_exists($parameterName, $args)) {
            $resolvedValue = $args[$parameterName];
        } elseif (!$variadic && array_key_exists($parameterPosition, $args)) {
            $resolvedValue = $args[$parameterPosition];
        } elseif ($variadic) {
            $values = [];
            foreach ($args as $key => $value) {
                if (is_int($key) && $key >= $parameterPosition) {
                    $values[] = $value;
                }
            }

            if ($values !== []) {
                $resolvedValue = $values;
            }
        }

        return $resolvedValue;
    }

    /**
     * Check whether a class opts out of injector-managed caching.
     *
     * @template TObject of object
     * @param ReflectionClass<TObject> $reflectionClass
     */
    public static function isTransient(ReflectionClass $reflectionClass): bool
    {
        return $reflectionClass->getAttributes(Transient::class) !== [];
    }

    /**
     * Detect a mixture of integer and string keys in an argument array.
     *
     * @param array<mixed> $arr
     */
    private static function arrayHasMixedKeys(array $arr): bool
    {
        $hasIntegerKeys = false;
        $hasStringKeys = false;

        foreach (array_keys($arr) as $key) {
            if (is_int($key)) {
                $hasIntegerKeys = true;
            } elseif (is_string($key)) {
                $hasStringKeys = true;
            }

            if ($hasIntegerKeys && $hasStringKeys) {
                return true;
            }
        }

        return false;
    }
}
