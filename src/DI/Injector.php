<?php

declare(strict_types=1);

namespace PHPInjector\DI;

use Closure;
use PHPInjector\Container\Container;
use PHPInjector\Contracts\Singleton;
use PHPInjector\Exceptions\InjectorException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Resolve PHP classes and callables through reflection and provider lookup.
 *
 * The most recently constructed injector is active. When a new injector has
 * no explicit parent, provider lookup continues through the previous active injector.
 * Automatically constructed classes are cached within their injector unless
 * their concrete class has the #[Transient] attribute.
 */
class Injector
{
    private const string ERR_NO_VALID_INJECTION_TARGET = 'No valid injection target provided.';

    private static ?self $instance = null;

    private readonly ?self $parent;

    /**
     * @var Container<mixed|object>
     */
    private readonly Container $providers;
    private readonly object $missingValue;

    /**
     * Create an injector with optional providers and an optional parent injector.
     *
     * When no parent is supplied, the previously active injector becomes
     * this injector's parent. The new injector then becomes active.
     *
     * @param array<class-string|int|string, mixed>|Container<mixed> $providers
     * @param ?self $parent Provider lookup continues through this injector when
     *     the current injector has no matching identifier.
     * @throws \PHPInjector\Container\ContainerException When list-style
     *     providers contain invalid or duplicate identifiers.
     */
    public function __construct(array|Container $providers = [], ?self $parent = null)
    {
        $this->providers = ($providers instanceof Container) ? $providers : new Container($providers);
        $this->parent = $parent ?? self::$instance;
        $this->missingValue = new \stdClass();
        self::$instance = $this;
    }

    /**
     * Resolve an injection target through the active injector.
     *
     * Supported targets include class names, class/object method arrays,
     * callable strings, closures, and invokable objects. Each reflected
     * parameter checks explicit arguments (type key, name, position), then
     * #[Inject], a typed provider, its PHP default, and finally an exception.
     * Provider lookup checks the active injector before its parent chain.
     *
     * @param array<int|string, mixed>|callable|class-string|object|string $injectionTarget
     * @param array<int|string, mixed> $args
     * @throws InjectorException When the target, callable, method, provider,
     *     or required parameter cannot be resolved.
     */
    public static function inject(array|callable|object|string $injectionTarget, array $args = []): mixed
    {
        $injector = self::instance();
        InjectorHelper::assertValidInjectionInput($injectionTarget, $args);

        if (\is_callable($injectionTarget)) {
            return InjectorHelper::injectCallable(
                $injectionTarget,
                $args,
                fn (object|string $id, array $methodArgs = [], ?string $methodName = null): mixed
                    => $injector->injectObject($id, $methodArgs, $methodName),
                fn (Closure|string $function, array $functionArgs = []): mixed
                    => InjectorHelper::injectFunction(
                        $function,
                        $functionArgs,
                        fn (ReflectionMethod|ReflectionFunction $reflector, array $resolvedArgs = []): array
                            => $injector->resolveParameters($reflector, $resolvedArgs)
                    )
            );
        }

        return $injector->injectNonCallableTarget($injectionTarget, $args);
    }

    /**
     * Register or replace a provider in this injector.
     *
     * The value may be a concrete object, scalar, closure, or class-string
     * provider. Class strings are instantiated lazily when they are resolved.
     */
    public function addProvider(string $id, mixed $value): void
    {
        $this->providers->set($id, $value);
    }

    /**
     * Reflect an object or class and dispatch it to constructor or method injection.
     *
     * @param array<int|string, mixed> $args
     */
    private function injectObject(object|string $id, array $args = [], ?string $methodName = null): mixed
    {
        /** @var class-string<object>|object $id */
        $reflectionClass = new ReflectionClass($id);

        if ($reflectionClass->implementsInterface(Singleton::class) && $methodName === null) {
            $methodName = 'getInstance';
        }

        if ($methodName === null) {
            return InjectorHelper::instantiateClass(
                $reflectionClass,
                $args,
                fn (ReflectionMethod|ReflectionFunction $reflector, array $resolvedArgs = []): array
                    => $this->resolveParameters($reflector, $resolvedArgs)
            );
        }

        if ($reflectionClass->hasMethod($methodName)) {
            return $this->invokeDefinedMethod($reflectionClass, $id, $methodName, $args);
        }

        return $this->invokeMagicMethod($reflectionClass, $id, $methodName, $args);
    }

    /**
     * Resolve every reflected parameter using the documented precedence order.
     * Each parameter stops at the first match: explicit argument, #[Inject],
     * typed provider, PHP default, or required-parameter exception.
     *
     * @param ReflectionMethod|ReflectionFunction $reflector
     * @param array<int|string, mixed> $args
     * @return array<int, mixed>
     */
    private function resolveParameters(ReflectionMethod|ReflectionFunction $reflector, array $args = []): array
    {
        $resolvedParameters = [];
        foreach ($reflector->getParameters() as $reflectionParameter) {
            foreach (self::resolveParameterValues($reflectionParameter, $args) as $value) {
                $resolvedParameters[] = $value;
            }
        }

        return $resolvedParameters;
    }

    /**
     * Route a non-callable target to method, object, or class resolution.
     *
     * @param array<int|string, mixed>|class-string|object|string $injectionTarget
     * @param array<int|string, mixed> $args
     */
    private function injectNonCallableTarget(array|object|string $injectionTarget, array $args): mixed
    {
        if (InjectorHelper::isMethodTarget($injectionTarget)) {
            /** @var array{0: class-string|object, 1: string} $injectionTarget */
            return $this->injectObject($injectionTarget[0], $args, $injectionTarget[1]);
        }

        if (\is_object($injectionTarget)) {
            return $this->injectObject($injectionTarget, $args);
        }

        if (\is_string($injectionTarget)) {
            return $this->resolveStringTarget($injectionTarget, $args);
        }

        throw new InjectorException(self::ERR_NO_VALID_INJECTION_TARGET);
    }

    /**
     * Look up a string identifier and fall back to direct class construction.
     *
     * @param array<int|string, mixed> $args
     */
    private function resolveStringTarget(string $injectionTarget, array $args): mixed
    {
        $provider = $this->getProvider($injectionTarget);

        if ($provider !== $this->missingValue) {
            if (\is_string($provider) && \class_exists($provider)) {
                return $this->instantiateAndStore($injectionTarget, $args, $provider);
            }

            return $provider;
        }

        if (\class_exists($injectionTarget)) {
            return $this->instantiateAndStore($injectionTarget, $args);
        }

        throw new InjectorException(self::ERR_NO_VALID_INJECTION_TARGET);
    }

    /**
     * Construct a concrete class and cache it under its concrete and alias IDs.
     *
     * Transient classes skip both cache writes. Existing provider objects are
     * always returned as supplied, including when their class is marked transient.
     *
     * @param array<int|string, mixed> $args
     */
    private function instantiateAndStore(
        string $storageId,
        array $args,
        ?string $className = null
    ): mixed {
        $resolvedClassName = $className ?? $storageId;

        /** @var class-string $resolvedClassName */
        $reflectionClass = new ReflectionClass($resolvedClassName);
        $isTransient = InjectorHelper::isTransient($reflectionClass);

        if (!$isTransient && $this->providers->has($resolvedClassName)) {
            $instance = $this->providers->get($resolvedClassName);

            if (\is_object($instance)) {
                if ($storageId !== $resolvedClassName) {
                    $this->addProvider($storageId, $instance);
                }

                return $instance;
            }
        }

        $instance = $this->injectObject($resolvedClassName, $args);

        /** @var object $instance */
        if (!$isTransient) {
            $this->addProvider($resolvedClassName, $instance);

            if ($storageId !== $resolvedClassName) {
                $this->addProvider($storageId, $instance);
            }
        }

        return $instance;
    }

    /**
     * Invoke a declared public method, constructor, or static method.
     *
     * @param ReflectionClass<object> $reflectionClass
     * @param array<int|string, mixed> $args
     */
    private function invokeDefinedMethod(
        ReflectionClass $reflectionClass,
        object|string $id,
        string $methodName,
        array $args
    ): mixed {
        $reflectionMethod = $reflectionClass->getMethod($methodName);
        InjectorHelper::assertPublicMethod($reflectionMethod, $reflectionClass->name, $methodName);

        $resolvedParameters = $this->resolveParameters($reflectionMethod, $args);

        if ($reflectionMethod->isStatic()) {
            return $reflectionMethod->invokeArgs(null, $resolvedParameters);
        }

        if ($reflectionMethod->isConstructor()) {
            return $reflectionClass->newInstanceArgs($resolvedParameters);
        }

        return $reflectionMethod->invokeArgs(
            $this->resolveTargetObject($reflectionClass, $id, $args),
            $resolvedParameters
        );
    }

    /**
     * Invoke __call() or __callStatic() when the requested method is undefined.
     *
     * @param ReflectionClass<object> $reflectionClass
     * @param array<int|string, mixed> $args
     */
    private function invokeMagicMethod(
        ReflectionClass $reflectionClass,
        object|string $id,
        string $methodName,
        array $args
    ): mixed {
        $magicMethod = InjectorHelper::resolveMagicMethodName($reflectionClass, $id);

        if ($magicMethod === null) {
            throw new InjectorException(
                "The method '$methodName' is undefined in class '{$reflectionClass->name}'."
            );
        }

        $reflectionMethod = $reflectionClass->getMethod($magicMethod);
        InjectorHelper::assertPublicMethod($reflectionMethod, $reflectionClass->name, $magicMethod, true);

        if ($reflectionMethod->isStatic()) {
            return $reflectionMethod->invokeArgs(null, [$methodName, $args]);
        }

        return $reflectionMethod->invokeArgs(
            $this->resolveTargetObject($reflectionClass, $id, $args),
            [$methodName, $args]
        );
    }

    /**
     * Obtain the object on which an instance method should run.
     *
     * @param ReflectionClass<object> $reflectionClass
     * @param array<int|string, mixed> $args
     */
    private function resolveTargetObject(ReflectionClass $reflectionClass, object|string $id, array $args): object
    {
        if (\is_object($id)) {
            return $id;
        }

        $target = $this->resolveStringTarget($reflectionClass->name, $args);

        if (!\is_object($target)) {
            throw new InjectorException(
                "Target '{$reflectionClass->name}' did not resolve to an object."
            );
        }

        return $target;
    }

    /**
     * Resolve one parameter in the lookup order used by every injection target.
     * Explicit arguments are checked by type key, parameter name, then numeric
     * position. If none matches, the resolver checks #[Inject], a typed provider,
     * the PHP default, and finally throws for an unresolved required parameter.
     * Variadic parameters collect positional values from their position onward,
     * and array-valued providers are expanded into individual arguments.
     *
     * @param array<int|string, mixed> $args
     * @return array<int, mixed>
     */
    private function resolveParameterValues(\ReflectionParameter $reflectionParameter, array $args): array
    {
        $parameterType = $reflectionParameter->getType();
        $parameterTypeName = InjectorHelper::resolveParameterTypeName($parameterType);
        $resolvedValues = [];
        $provider = InjectorHelper::getArgumentValue(
            $args,
            $parameterTypeName,
            $reflectionParameter->name,
            $reflectionParameter->getPosition(),
            $this->missingValue,
            $reflectionParameter->isVariadic()
        );

        if ($provider === $this->missingValue) {
            $provider = InjectorHelper::resolveProviderFromAttribute(
                $reflectionParameter,
                fn (string $id): mixed => $this->getProvider($id),
                $this->missingValue
            );
        }

        if ($provider === $this->missingValue) {
            $provider = InjectorHelper::resolveProviderFromType(
                $parameterType,
                fn (string $id, ReflectionNamedType $type): mixed => $this->getProvider($id, $type),
                $this->missingValue
            );
        }

        if ($provider === $this->missingValue) {
            if (!$reflectionParameter->isVariadic()) {
                if ($reflectionParameter->isDefaultValueAvailable()) {
                    $resolvedValues = [$reflectionParameter->getDefaultValue()];
                } else {
                    throw new InjectorException(
                        InjectorHelper::buildMissingParameterMessage($reflectionParameter, $parameterTypeName)
                    );
                }
            }
        } elseif (!$reflectionParameter->isVariadic()) {
            $resolvedValues = [$provider];
        } else {
            $resolvedValues = \is_array($provider) ? \array_values($provider) : [$provider];
        }

        return $resolvedValues;
    }

    /**
     * Find a provider in this injector or its parent chain, checking the
     * current injector first.
     *
     * Class-string providers requested for non-builtin typed parameters are
     * resolved before being returned so their concrete instance can be cached.
     */
    private function getProvider(string $id, ?ReflectionNamedType $type = null): mixed
    {
        $injector = $this;

        while ($injector instanceof self) {
            if ($injector->providers->has($id)) {
                $provider = $injector->providers->get($id);

                if (
                    $type instanceof ReflectionNamedType
                    && !$type->isBuiltin()
                    && \is_string($provider)
                ) {
                    $provider = $injector->resolveStringTarget($provider, []);
                }

                return $provider;
            }

            $injector = $injector->parent;
        }

        return $this->missingValue;
    }

    private static function instance(): self
    {
        return self::$instance ??= new self();
    }
}
