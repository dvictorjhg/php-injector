<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Mock;

use Closure;
use PHPInjector\DI\Attributes\Inject;

/**
 * Method signatures used to exercise constructor, method, scalar, and variadic injection paths.
 */
class Methods
{
    public function __construct(public ?SimpleProvider $provider = null)
    {
    }

    public function methodWithSimpleProviderClassParameter(SimpleProvider $simpleProvider): SimpleProvider
    {
        return $simpleProvider;
    }

    public static function staticMethodWithSimpleProviderClassParameter(SimpleProvider $simpleProvider): SimpleProvider
    {
        return $simpleProvider;
    }

    public static function staticMethodWithMixedParameters(
        SimpleProvider $simpleProvider,
        bool $boolDefaultFalse = false,
        bool $boolDefaultTrue = true,
        ?SingletonProvider $singletonProvider = null
    ): bool {
        if ($simpleProvider->providedIn === '') {
            return false;
        }

        if ($boolDefaultFalse && $boolDefaultTrue && $singletonProvider instanceof SingletonProvider) {
            return true;
        }

        return false;
    }

    public static function staticMethodWithProviderClassParameter(Provider $provider): Provider
    {
        return $provider;
    }

    public static function staticMethodWithIntParameterProvidedViaInjectParameterAttribute(#[Inject('n')] int $n): int
    {
        return $n;
    }

    public static function staticMethodWithClosureParameterProvidedViaInjectParameterAttribute(
        #[Inject('closure')] Closure $closure
    ): Closure {
        return $closure;
    }

    public static function staticMethodWithClosureParameter(Closure $closure): Closure
    {
        return $closure;
    }

    public static function staticMethodWithProviderWithRequiredProviderClassParameter(
        ProviderWithRequiredProvider $providerWithRequiredProvider
    ): ProviderWithRequiredProvider {
        return $providerWithRequiredProvider;
    }

    public static function staticVoidMethodWithoutParameters(): void
    {
        // Intentionally empty to validate void static callable injection.
    }

    public static function staticMethodWithDefaultValuedParameter(true $param1 = true): true
    {
        return $param1;
    }

    public static function staticMethodWithNullableParameter(?string $value = 'fallback'): ?string
    {
        return $value;
    }

    public static function staticMethodWithNullableParameterProvidedViaInjectParameterAttribute(
        #[Inject('nullable')] ?string $value = 'fallback'
    ): ?string {
        return $value;
    }

    public static function staticMethodWithVariableLengthArgumentList(SimpleProvider ...$provider): int
    {
        return \count($provider);
    }

    protected static function staticProtectedMethod(SimpleProvider $simpleProvider): SimpleProvider
    {
        return $simpleProvider;
    }

    // @phpstan-ignore method.unused
    private static function staticPrivateMethod(SimpleProvider $simpleProvider): SimpleProvider // NOSONAR
    {
        return $simpleProvider;
    }
}
