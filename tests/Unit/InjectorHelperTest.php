<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Unit;

use Closure;
use PHPInjector\DI\InjectorHelper;
use PHPInjector\Exceptions\InjectorException;
use PHPInjector\Tests\Mock\MagicMethods;
use PHPInjector\Tests\Mock\Methods;
use PHPInjector\Tests\Mock\Provider;
use PHPInjector\Tests\Mock\SimpleProvider;
use PHPInjector\Tests\Mock\TransientProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

#[CoversClass(InjectorHelper::class)]
final class InjectorHelperTest extends TestCase
{
    public function testAssertValidInjectionInputAcceptsHomogeneousArguments(): void
    {
        InjectorHelper::assertValidInjectionInput(Methods::class, ['name' => 'value']);

        $this->addToAssertionCount(1);
    }

    public function testAssertValidInjectionInputRejectsEmptyTarget(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('Non-injectable injection target provided.');

        InjectorHelper::assertValidInjectionInput('', []);
    }

    public function testAssertValidInjectionInputRejectsMixedArgumentKeys(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('The provided $args array contains mixed keys.');

        InjectorHelper::assertValidInjectionInput(Methods::class, [0 => 'value', 'name' => 'other']);
    }

    public function testIsMethodTargetRecognizesValidTargets(): void
    {
        /** @var mixed $staticTarget */
        $staticTarget = [Methods::class, 'staticVoidMethodWithoutParameters'];
        /** @var mixed $objectTarget */
        $objectTarget = [new Methods(), 'methodWithSimpleProviderClassParameter'];

        $this->assertTrue(InjectorHelper::isMethodTarget($staticTarget));
        $this->assertTrue(InjectorHelper::isMethodTarget($objectTarget));
    }

    public function testIsMethodTargetRejectsInvalidTargets(): void
    {
        /** @var mixed $missingMethod */
        $missingMethod = [Methods::class];
        /** @var mixed $unknownClass */
        $unknownClass = ['UnknownClass', 'method'];
        /** @var mixed $invalidMethodName */
        $invalidMethodName = [Methods::class, 1];

        $this->assertFalse(InjectorHelper::isMethodTarget($missingMethod));
        $this->assertFalse(InjectorHelper::isMethodTarget($unknownClass));
        $this->assertFalse(InjectorHelper::isMethodTarget($invalidMethodName));
    }

    public function testInjectCallableRoutesStaticMethodStringsToObjectInjection(): void
    {
        $result = $this->assertCallableRoutesToObjectInjection(
            Methods::class . '::staticVoidMethodWithoutParameters',
            ['flag' => true]
        );

        $this->assertSame([Methods::class, 'staticVoidMethodWithoutParameters', ['flag' => true]], $result);
    }

    public function testInjectCallableRoutesFunctionStringsToFunctionInjection(): void
    {
        $result = $this->assertCallableRoutesToFunctionInjection('strlen', ['string' => 'hello']);

        $this->assertSame(['strlen', ['string' => 'hello']], $result);
    }

    public function testInjectCallableRoutesArrayCallablesToObjectInjection(): void
    {
        $result = $this->assertCallableRoutesToObjectInjection(
            [Methods::class, 'staticVoidMethodWithoutParameters'],
            []
        );

        $this->assertSame([Methods::class, 'staticVoidMethodWithoutParameters', []], $result);
    }

    public function testInjectCallableRoutesClosuresToFunctionInjection(): void
    {
        $closure = static fn (): string => 'ok';

        $result = $this->assertCallableRoutesToFunctionInjection($closure, ['name' => 'value']);

        $this->assertSame([$closure, ['name' => 'value']], $result);
    }

    public function testInjectCallableRoutesInvocableObjectsToObjectInjection(): void
    {
        $callable = new class {
            public function __invoke(): string
            {
                return 'ok';
            }
        };

        $result = $this->assertCallableRoutesToObjectInjection($callable, ['name' => 'value']);

        $this->assertSame([$callable, '__invoke', ['name' => 'value']], $result);
    }

    public function testInjectFunctionInvokesResolvedParameters(): void
    {
        $result = InjectorHelper::injectFunction(
            'strlen',
            ['string' => 'hello'],
            static fn (mixed $_reflector = null, array $_args = []): array => ['hello']
        );

        $this->assertSame(5, $result);
    }

    public function testInjectFunctionWrapsReflectionErrors(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('Cannot create a ReflectionFunction: Function missing_function_for_injector_helper_tests() does not exist');

        InjectorHelper::injectFunction(
            'missing_function_for_injector_helper_tests',
            [],
            static fn (mixed $_reflector = null, array $_args = []): array => []
        );
    }

    public function testInstantiateClassBuildsObjectsWithAndWithoutConstructors(): void
    {
        $parameterResolutionWasCalled = false;
        $withoutConstructor = InjectorHelper::instantiateClass(
            new ReflectionClass(Provider::class),
            [],
            static function (mixed $_reflector = null, array $_args = []) use (&$parameterResolutionWasCalled): array {
                $parameterResolutionWasCalled = $_reflector instanceof ReflectionMethod && $_args === [];

                return [];
            }
        );

        $this->assertFalse($parameterResolutionWasCalled);

        $parameterResolutionWasCalled = false;
        $withConstructor = InjectorHelper::instantiateClass(
            new ReflectionClass(SimpleProvider::class),
            ['providedIn' => 'tests'],
            static function (mixed $reflector = null, array $args = []) use (&$parameterResolutionWasCalled): array {
                self::assertInstanceOf(ReflectionMethod::class, $reflector);
                self::assertSame(['providedIn' => 'tests'], $args);
                $parameterResolutionWasCalled = true;

                return ['tests'];
            }
        );

        $this->assertInstanceOf(Provider::class, $withoutConstructor);
        $this->assertInstanceOf(SimpleProvider::class, $withConstructor);
        $this->assertTrue($parameterResolutionWasCalled);
        $this->assertSame('tests', $withConstructor->providedIn);
    }

    public function testResolveMagicMethodNamePrefersExpectedMagicMethodByContext(): void
    {
        $reflectionClass = new ReflectionClass(MagicMethods::class);

        $this->assertSame('__callStatic', InjectorHelper::resolveMagicMethodName($reflectionClass, MagicMethods::class));
        $this->assertSame('__call', InjectorHelper::resolveMagicMethodName($reflectionClass, new MagicMethods()));
    }

    public function testResolveMagicMethodNameReturnsNullWhenClassHasNoMagicMethodFallback(): void
    {
        $reflectionClass = new ReflectionClass(Methods::class);

        $this->assertNull(InjectorHelper::resolveMagicMethodName($reflectionClass, Methods::class));
    }

    public function testAssertPublicMethodAcceptsPublicMethods(): void
    {
        $reflectionMethod = new ReflectionMethod(Methods::class, 'staticVoidMethodWithoutParameters');

        InjectorHelper::assertPublicMethod($reflectionMethod, Methods::class, $reflectionMethod->name);

        $this->addToAssertionCount(1);
    }

    public function testAssertPublicMethodRejectsNonPublicMethods(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(
            "Method 'staticProtectedMethod' of class 'PHPInjector\\Tests\\Mock\\Methods' is not accessible."
        );

        $reflectionMethod = new ReflectionMethod(Methods::class, 'staticProtectedMethod');

        InjectorHelper::assertPublicMethod($reflectionMethod, Methods::class, $reflectionMethod->name);
    }

    public function testResolveParameterTypeNameHandlesBuiltinAndNamedTypes(): void
    {
        $classParameter = $this->parameterFromMethod('staticMethodWithSimpleProviderClassParameter');
        $builtinParameter = $this->parameterFromMethod('staticMethodWithIntParameterProvidedViaInjectParameterAttribute');
        $closureParameter = $this->parameterFromMethod('staticMethodWithClosureParameterProvidedViaInjectParameterAttribute');

        $this->assertSame(SimpleProvider::class, InjectorHelper::resolveParameterTypeName($classParameter->getType()));
        $this->assertSame('', InjectorHelper::resolveParameterTypeName($builtinParameter->getType()));
        $this->assertSame(Closure::class, InjectorHelper::resolveParameterTypeName($closureParameter->getType()));
    }

    public function testBuildMissingParameterMessageIncludesResolvedTypeName(): void
    {
        $parameter = $this->parameterFromMethod('staticMethodWithSimpleProviderClassParameter');

        $message = InjectorHelper::buildMissingParameterMessage($parameter, SimpleProvider::class);

        $this->assertSame(
            'No provider or value found for parameter: PHPInjector\\Tests\\Mock\\SimpleProvider $simpleProvider at position 0.',
            $message
        );
    }

    public function testResolveProviderFromAttributeReturnsMissingValueWhenAttributeIsAbsent(): void
    {
        $parameter = $this->parameterFromMethod('staticMethodWithSimpleProviderClassParameter');
        $missingValue = $this->missingValue();

        $result = InjectorHelper::resolveProviderFromAttribute(
            $parameter,
            static fn (string $id): string => $id,
            $missingValue
        );

        $this->assertSame($missingValue, $result);
    }

    public function testResolveProviderFromAttributeReturnsResolvedProvider(): void
    {
        $parameter = $this->parameterFromMethod('staticMethodWithIntParameterProvidedViaInjectParameterAttribute');
        $resolvedId = null;

        $result = InjectorHelper::resolveProviderFromAttribute(
            $parameter,
            static function (string $id) use (&$resolvedId): string {
                $resolvedId = $id;

                return 'value for ' . $id;
            },
            $this->missingValue()
        );

        $this->assertSame('n', $resolvedId);
        $this->assertSame('value for n', $result);
    }

    public function testResolveProviderFromTypeReturnsMissingValueForBuiltinTypes(): void
    {
        $parameter = $this->parameterFromMethod('staticMethodWithIntParameterProvidedViaInjectParameterAttribute');
        $missingValue = $this->missingValue();

        $result = InjectorHelper::resolveProviderFromType(
            $parameter->getType(),
            static fn (): string => 'resolved',
            $missingValue
        );

        $this->assertSame($missingValue, $result);
    }

    public function testResolveProviderFromTypeReturnsResolvedProviderForNamedTypes(): void
    {
        $parameter = $this->parameterFromMethod('staticMethodWithSimpleProviderClassParameter');
        $receivedType = null;

        $result = InjectorHelper::resolveProviderFromType(
            $parameter->getType(),
            static function (string $id, ReflectionNamedType $type) use (&$receivedType): string {
                $receivedType = $type;

                return 'value for ' . $id;
            },
            new \stdClass()
        );

        $this->assertSame('value for ' . SimpleProvider::class, $result);
        $this->assertInstanceOf(ReflectionNamedType::class, $receivedType);
    }

    public function testGetArgumentValuePrefersTypeThenNameThenPosition(): void
    {
        $missingValue = $this->missingValue();

        $this->assertSame(
            'from-type',
            InjectorHelper::getArgumentValue([
                SimpleProvider::class => 'from-type',
                'simpleProvider' => 'from-name',
                0 => 'from-position',
            ], SimpleProvider::class, 'simpleProvider', 0, $missingValue)
        );

        $this->assertSame(
            'from-name',
            InjectorHelper::getArgumentValue(['simpleProvider' => 'from-name'], SimpleProvider::class, 'simpleProvider', 0, $missingValue)
        );

        $this->assertSame(
            'from-position',
            InjectorHelper::getArgumentValue([0 => 'from-position'], SimpleProvider::class, 'simpleProvider', 0, $missingValue)
        );

        $this->assertSame(
            $missingValue,
            InjectorHelper::getArgumentValue([], SimpleProvider::class, 'simpleProvider', 0, $missingValue)
        );
    }

    private function parameterFromMethod(string $methodName): \ReflectionParameter
    {
        return $this->reflectionMethod($methodName)->getParameters()[0];
    }

    private function reflectionMethod(string $methodName): ReflectionMethod
    {
        return new ReflectionMethod(Methods::class, $methodName);
    }

    public function testIsTransientReturnsFalseForClassWithNoTransientAttribute(): void
    {
        $result = InjectorHelper::isTransient(new ReflectionClass(SimpleProvider::class));

        $this->assertFalse($result);
    }

    public function testIsTransientReturnsTrueForClassWithTransientAttribute(): void
    {
        $result = InjectorHelper::isTransient(new ReflectionClass(TransientProvider::class));

        $this->assertTrue($result);
    }

    private function missingValue(): object
    {
        return new \stdClass();
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable|string $callable
     * @param array<int|string, mixed> $args
     * @return array{0: object|string, 1: string|null, 2: array<int|string, mixed>}
     */
    private function assertCallableRoutesToObjectInjection(array|callable|string $callable, array $args): array
    {
        $objectCalls = 0;
        $functionCalls = 0;
        self::assertTrue(is_callable($callable));

        /** @var callable $callableTarget */
        $callableTarget = $callable;

        $result = InjectorHelper::injectCallable(
            $callableTarget,
            $args,
            static function (object|string $id, array $receivedArgs, ?string $methodName) use (&$objectCalls): array {
                $objectCalls++;

                return [$id, $methodName, $receivedArgs];
            },
            static function () use (&$functionCalls): string {
                $functionCalls++;

                return 'function';
            }
        );

        $this->assertSame(1, $objectCalls);
        $this->assertSame(0, $functionCalls);

        /** @var array{0: object|string, 1: string|null, 2: array<int|string, mixed>} $result */
        return $result;
    }

    /**
     * @param callable|string $callable
     * @param array<int|string, mixed> $args
     * @return array{0: Closure|string, 1: array<int|string, mixed>}
     */
    private function assertCallableRoutesToFunctionInjection(callable|string $callable, array $args): array
    {
        $objectCalls = 0;
        $functionCalls = 0;
        self::assertTrue(is_callable($callable));

        /** @var callable $callableTarget */
        $callableTarget = $callable;

        $result = InjectorHelper::injectCallable(
            $callableTarget,
            $args,
            static function () use (&$objectCalls): string {
                $objectCalls++;

                return 'object';
            },
            static function (Closure|string $function, array $receivedArgs) use (&$functionCalls): array {
                $functionCalls++;

                return [$function, $receivedArgs];
            }
        );

        $this->assertSame(0, $objectCalls);
        $this->assertSame(1, $functionCalls);

        /** @var array{0: Closure|string, 1: array<int|string, mixed>} $result */
        return $result;
    }
}
