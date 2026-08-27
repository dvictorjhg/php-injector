<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Unit;

use PHPInjector\DI\Attributes\Inject;
use PHPInjector\DI\Attributes\Transient;
use PHPInjector\DI\Injector;
use PHPInjector\Exceptions\InjectorException;
use PHPInjector\Tests\Mock\MagicMethods;
use PHPInjector\Tests\Mock\Methods;
use PHPInjector\Tests\Mock\Provider;
use PHPInjector\Tests\Mock\ProviderWithRequiredProvider;
use PHPInjector\Tests\Mock\SimpleProvider;
use PHPInjector\Tests\Mock\SingletonConsumer;
use PHPInjector\Tests\Mock\SingletonProvider;
use PHPInjector\Tests\Mock\TransientProvider;
use PHPInjector\Tests\Mock\TransientSingletonProvider;
use function PHPInjector\DI\inject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[CoversClass(Injector::class)]
#[CoversClass(Inject::class)]
#[CoversClass(Transient::class)]
#[CoversFunction('PHPInjector\\DI\\inject')]
#[RunTestsInSeparateProcesses]
final class InjectorTest extends TestCase
{
    private const string TEST_INJECTOR_CONSTRUCT = 'TEST_INJECTOR_CONSTRUCT';
    private const string TEST_GLOBAL_INJECTION = 'TEST_GLOBAL_INJECTION';
    private const string TEST_CLASS_INJECTION = 'TEST_CLASS_INJECTION';
    private const string TEST_LIFECYCLE = 'TEST_LIFECYCLE';
    private const string TEST_OBJECT_INJECTION = 'TEST_OBJECT_INJECTION';
    private const string TEST_METHOD_INJECTION = 'TEST_METHOD_INJECTION';
    private const string TEST_CALLABLE_INJECTION = 'TEST_CALLABLE_INJECTION';
    private const string TEST_INJECTOR_EXCEPTION = 'TEST_INJECTOR_EXCEPTION';
    private const string TEST_SCALAR_INJECTION = 'TEST_SCALAR_INJECTION';
    private const string TEST_INJECT_ATTRIBUTE = 'TEST_INJECT_ATTRIBUTE';
    private const string ERR_NO_VALID_INJECTION_TARGET = 'No valid injection target provided.';

    #[Group(self::TEST_INJECTOR_CONSTRUCT)]
    public function testNewInjectorWithoutProviders(): void
    {
        $injector = $this->createInjector();

        $this->assertInstanceOf(Injector::class, $injector);
    }

    #[Group(self::TEST_GLOBAL_INJECTION)]
    public function testStaticInjectionUsesTheNewestInjectorAndItsParentStaircase(): void
    {
        $this->createInjector(['parent-value' => 'from-parent']);
        $this->createInjector(['child-value' => 'from-child']);

        $this->assertSame('from-child', Injector::inject('child-value'));
        $this->assertSame('from-parent', Injector::inject('parent-value'));
    }

    #[Group(self::TEST_GLOBAL_INJECTION)]
    public function testGlobalFunctionUsesTheActiveInjector(): void
    {
        $this->createInjector(['value' => 'from-injector']);

        $this->assertSame('from-injector', inject('value'));
        $this->assertSame('from-injector', Injector::inject('value'));
    }

    #[Group(self::TEST_GLOBAL_INJECTION)]
    public function testStaticInjectionCreatesAndReusesTheDefaultInjector(): void
    {
        $firstProvider = inject(SimpleProvider::class);
        $secondProvider = inject(SimpleProvider::class);

        $this->assertInstanceOf(SimpleProvider::class, $firstProvider);
        $this->assertSame($firstProvider, $secondProvider);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectClassWithNonInstantiatedProviderCachesResolvedProviderInstance(): void
    {
        $injector = $this->createInjector([SimpleProvider::class]);
        $firstConsumer = $this->assertMethodsConsumerUsesSimpleProvider($injector->inject(Methods::class));
        $secondConsumer = $this->assertMethodsConsumerUsesSimpleProvider($injector->inject(Methods::class));

        $this->assertSame($firstConsumer->provider, $secondConsumer->provider);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectClassWithInstantiatedProviderReusesProvidedInstance(): void
    {
        $configuredProvider = $this->simpleProvider('prebuilt-provider');
        $injector = $this->createInjector([$configuredProvider]);
        $providerConsumer = $this->assertMethodsConsumerUsesSimpleProvider($injector->inject(Methods::class));

        $this->assertSame($configuredProvider, $providerConsumer->provider);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectClassWithNonInstantiatedSingletonProviderUsesSingletonFactoryInstance(): void
    {
        $injector = $this->createInjector([SingletonProvider::class]);
        $singletonConsumer = $this->assertSingletonConsumerUsesSingletonProvider(
            $injector->inject(SingletonConsumer::class)
        );

        $this->assertSame(SingletonProvider::getInstance(), $singletonConsumer->singletonProvider);
        $this->assertSame('B', $singletonConsumer->singletonProvider->instanceValue);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectClassWithInstantiatedSingletonProviderKeepsCallerOwnedState(): void
    {
        $singletonProvider = SingletonProvider::getInstance();
        $originalValue = $singletonProvider->instanceValue;

        $singletonProvider->instanceValue = 'preconfigured';

        try {
            $injector = $this->createInjector([$singletonProvider]);
            $singletonConsumer = $this->assertSingletonConsumerUsesSingletonProvider(
                $injector->inject(SingletonConsumer::class)
            );

            $this->assertSame($singletonProvider, $singletonConsumer->singletonProvider);
            $this->assertSame('preconfigured', $singletonConsumer->singletonProvider->instanceValue);
        } finally {
            $singletonProvider->instanceValue = $originalValue;
        }
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectSingletonProviderReusesSharedStateAcrossConsumers(): void
    {
        $injector = $this->createInjector([SingletonProvider::class]);

        $firstConsumer = $this->assertSingletonConsumerUsesSingletonProvider(
            $injector->inject(SingletonConsumer::class)
        );
        $originalValue = $firstConsumer->singletonProvider->instanceValue;
        $firstConsumer->singletonProvider->instanceValue = 'mutated-through-first-consumer';

        try {
            $secondConsumer = $this->assertSingletonConsumerUsesSingletonProvider(
                $injector->inject(SingletonConsumer::class)
            );

            $this->assertSame($firstConsumer->singletonProvider, $secondConsumer->singletonProvider);
            $this->assertSame(
                'mutated-through-first-consumer',
                $secondConsumer->singletonProvider->instanceValue
            );
            $this->assertSame(SingletonProvider::getInstance(), $firstConsumer->singletonProvider);
        } finally {
            $firstConsumer->singletonProvider->instanceValue = $originalValue;
        }
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectClassWithProviderFromParentInjectorReusesParentProviderInstance(): void
    {
        $parentProvider = $this->simpleProvider('parent-injector');
        $parentInjector = $this->createInjector([$parentProvider]);
        $injectorWithParent = $this->createInjector([], $parentInjector);
        $providerConsumer = $this->assertMethodsConsumerUsesSimpleProvider($injectorWithParent->inject(Methods::class));

        $this->assertSame($parentProvider, $providerConsumer->provider);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testParentAliasResolvesAgainstTheParentInjectorProviders(): void
    {
        $parentProvider = $this->simpleProvider('parent-injector');
        $parentInjector = $this->createInjector([
            Provider::class => SimpleProvider::class,
            SimpleProvider::class => $parentProvider,
        ]);
        $childProvider = $this->simpleProvider('child-injector');
        $childInjector = $this->createInjector([SimpleProvider::class => $childProvider], $parentInjector);

        $resolvedProvider = $childInjector->inject([
            Methods::class,
            'staticMethodWithProviderClassParameter',
        ]);

        $this->assertSame($parentProvider, $resolvedProvider);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectAliasAndConcreteClassReuseTheSameSingletonInstance(): void
    {
        $injector = $this->createInjector([Provider::class => SimpleProvider::class]);

        $fromAlias = $injector->inject(Provider::class);
        $fromConcreteClass = $injector->inject(SimpleProvider::class);

        $this->assertInstanceOf(SimpleProvider::class, $fromAlias);
        $this->assertSame($fromAlias, $fromConcreteClass);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectAliasedProviderFromParentForTypedDependencySharesConcreteCache(): void
    {
        $parentInjector = $this->createInjector([Provider::class => SimpleProvider::class]);
        $injector = $this->createInjector([], $parentInjector);

        $provider = $injector->inject([Methods::class, 'staticMethodWithProviderClassParameter']);
        $fromConcreteClass = $injector->inject(SimpleProvider::class);

        $this->assertInstanceOf(SimpleProvider::class, $provider);
        $this->assertSame($provider, $fromConcreteClass);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectProviderFromMultipleParentLevels(): void
    {
        $rootInjector = $this->createInjector([SimpleProvider::class]);
        $parentInjector = $this->createInjector([], $rootInjector);
        $injector = $this->createInjector([], $parentInjector);

        $provider = $injector->inject([Methods::class, 'staticMethodWithSimpleProviderClassParameter']);

        $this->assertInstanceOf(SimpleProvider::class, $provider);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectClassWithoutConstructor(): void
    {
        $injector = $this->createInjector();
        $provider = $injector->inject(Provider::class);

        $this->assertInstanceOf(Provider::class, $provider);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectNonInstantiatedClassConstructArrayForm(): void
    {
        $injector = $this->createInjector();

        $providerConsumer = $injector->inject([Methods::class, '__construct']);

        $this->assertInstanceOf(Methods::class, $providerConsumer);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    public function testInjectInstantiatedObjectConstructArrayForm(): void
    {
        $injector = $this->createInjector();

        $providerConsumer = $injector->inject([new Methods(), '__construct']);

        $this->assertInstanceOf(Methods::class, $providerConsumer);
    }

    #[Group(self::TEST_CLASS_INJECTION)]
    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectReflectionClass(): void
    {
        $injector = $this->createInjector();
        $reflectedClassName = $injector->inject(
            [\ReflectionClass::class, 'getName'],
            ['objectOrClass' => new Methods()]
        );

        $this->assertEquals(Methods::class, $reflectedClassName);
    }

    #[Group(self::TEST_LIFECYCLE)]
    public function testTransientAttributeCreatesNewInstanceEachInjection(): void
    {
        $injector = $this->createInjector();

        $firstInstance = $injector->inject(TransientProvider::class);
        $secondInstance = $injector->inject(TransientProvider::class);

        $this->assertNotSame($firstInstance, $secondInstance);
    }

    #[Group(self::TEST_LIFECYCLE)]
    public function testDefaultBehaviorCachesInstances(): void
    {
        $injector = $this->createInjector();

        $firstInstance = $injector->inject(SimpleProvider::class);
        $secondInstance = $injector->inject(SimpleProvider::class);

        $this->assertSame($firstInstance, $secondInstance);
    }

    #[Group(self::TEST_LIFECYCLE)]
    public function testSingletonContractDelegatesConstructionToClassItselfAcrossAnyInjector(): void
    {
        // This fixture calls getInstance() instead of its private constructor and owns the
        // shared reference independently of each injector's cache.
        $firstInjector = $this->createInjector([SingletonProvider::class]);
        $fromFirst = $firstInjector->inject(SingletonProvider::class);

        $secondInjector = $this->createInjector([SingletonProvider::class]);
        $fromSecond = $secondInjector->inject(SingletonProvider::class);

        $this->assertSame($fromFirst, $fromSecond);
        $this->assertSame(SingletonProvider::getInstance(), $fromFirst);
    }

    #[Group(self::TEST_LIFECYCLE)]
    public function testDefaultResolutionCachesConstructedInstanceAcrossTheInjectorStaircase(): void
    {
        // The default scope is injector-managed: the injector constructs and caches in its own
        // provider state. A child injector reuses the cached instance from its parent.
        $firstInjector = $this->createInjector();
        $fromFirst = $firstInjector->inject(SimpleProvider::class);

        $secondInjector = $this->createInjector();
        $fromSecond = $secondInjector->inject(SimpleProvider::class);

        $this->assertSame($fromFirst, $fromSecond);
    }

    #[Group(self::TEST_LIFECYCLE)]
    public function testTransientAttributeDoesNotOverrideSingletonContract(): void
    {
        // #[Transient] only skips injector caching. This fixture's getInstance() method still
        // returns the same class-held reference on every resolution.
        $injector = $this->createInjector();

        $first = $injector->inject(TransientSingletonProvider::class);
        $second = $injector->inject(TransientSingletonProvider::class);

        $this->assertSame($first, $second);
        $this->assertSame(TransientSingletonProvider::getInstance(), $first);
    }

    #[Group(self::TEST_OBJECT_INJECTION)]
    public function testInjectObject(): void
    {
        $injectorProvider = $this->simpleProvider('injector');
        $injector = $this->createInjector([$injectorProvider]);
        $instanceProvider = $this->simpleProvider('instance');
        $instantiatedProviderConsumer = new Methods($instanceProvider);

        $injectedProviderConsumer = $injector->inject($instantiatedProviderConsumer);

        $this->assertInstanceOf(Methods::class, $injectedProviderConsumer);
        $this->assertInstanceOf(SimpleProvider::class, $injectedProviderConsumer->provider);
        $this->assertSame($injectorProvider, $injectedProviderConsumer->provider);
        $this->assertNotSame($instanceProvider, $injectedProviderConsumer->provider);
        $this->assertSame('injector', $injectedProviderConsumer->provider->providedIn);
        $this->assertSame($instanceProvider, $instantiatedProviderConsumer->provider);
        $this->assertNotSame($injectorProvider, $instantiatedProviderConsumer->provider);
        $this->assertSame('instance', $instantiatedProviderConsumer->provider->providedIn);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectNonStaticMethodWithProvider(): void
    {
        $provider = $this->simpleProvider('method-provider');
        $injector = $this->createInjector([$provider]);
        $injectedProvider = $injector->inject([new Methods(), 'methodWithSimpleProviderClassParameter']);

        $this->assertSame($provider, $injectedProvider);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithProvider(): void
    {
        $provider = $this->simpleProvider('static-method-provider');
        $injector = $this->createInjector([$provider]);
        $injectedProvider = $injector->inject([Methods::class, 'staticMethodWithSimpleProviderClassParameter']);

        $this->assertSame($provider, $injectedProvider);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithMixedParametersAssocArgs(): void
    {
        $injector = $this->createInjector([SimpleProvider::class, SingletonProvider::class]);
        $result = $injector->inject(
            [Methods::class, 'staticMethodWithMixedParameters'],
            ['boolDefaultFalse' => true]
        );

        $this->assertTrue($result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithMixedParametersPositionalArgs(): void
    {
        $injector = $this->createInjector([SimpleProvider::class, SingletonProvider::class]);
        $result = $injector->inject(
            [Methods::class, 'staticMethodWithMixedParameters'],
            [1 => true]
        );

        $this->assertTrue($result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithSubclassOfProvider(): void
    {
        $injector = $this->createInjector([Provider::class => SimpleProvider::class]);
        $injectedProvider = $injector->inject([Methods::class, 'staticMethodWithProviderClassParameter']);

        $this->assertInstanceOf(SimpleProvider::class, $injectedProvider);
        $this->assertInstanceOf(Provider::class, $injectedProvider);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectIntoAnonymousClassMethod(): void
    {
        $provider = $this->simpleProvider('anonymous-method');
        $injector = $this->createInjector([$provider]);

        $anonymousClass = new class {
            public SimpleProvider $provider;

            public function setProvider(SimpleProvider $provider): void
            {
                $this->provider = $provider;
            }
        };

        $injector->inject([$anonymousClass, 'setProvider']);

        $this->assertInstanceOf($anonymousClass::class, $anonymousClass);
        $this->assertSame($provider, $anonymousClass->provider);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    #[Group(self::TEST_INJECT_ATTRIBUTE)]
    #[Group(self::TEST_SCALAR_INJECTION)]
    public function testInjectStaticMethodWithScalarProviderViaInjectParameterAttribute(): void
    {
        $injector = $this->createInjector(['n' => 1]);
        $result = $injector->inject([Methods::class, 'staticMethodWithIntParameterProvidedViaInjectParameterAttribute']);

        $this->assertIsInt($result);
        $this->assertSame(1, $result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    #[Group(self::TEST_INJECT_ATTRIBUTE)]
    public function testInjectStaticMethodWithClosureProviderViaInjectParameterAttribute(): void
    {
        $closure = function (): int {
            return 1;
        };
        $injector = $this->createInjector([
            'closure' => $closure
        ]);
        $result = $injector->inject([Methods::class, 'staticMethodWithClosureParameterProvidedViaInjectParameterAttribute']);

        $this->assertSame($closure, $result);
        $this->assertSame(1, $result());
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithClosureProviderByType(): void
    {
        $closure = static fn (): int => 1;
        $injector = $this->createInjector([\Closure::class => $closure]);

        $result = $injector->inject([Methods::class, 'staticMethodWithClosureParameter']);

        $this->assertSame($closure, $result);
        $this->assertSame(1, $result());
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInitializationOfInterdependentProvidersReusesCachedDependency(): void
    {
        $injector = $this->createInjector([SimpleProvider::class, ProviderWithRequiredProvider::class]);
        $sharedDependency = $injector->inject(SimpleProvider::class);
        $injectedProvider = $injector->inject([Methods::class, 'staticMethodWithProviderWithRequiredProviderClassParameter']);

        $this->assertInstanceOf(ProviderWithRequiredProvider::class, $injectedProvider);
        $this->assertSame($sharedDependency, $injectedProvider->requiredProvider);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithoutParameters(): void
    {
        $injector = $this->createInjector();
        $result = $injector->inject([Methods::class, 'staticVoidMethodWithoutParameters']);

        $this->assertNull($result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithInputArgument(): void
    {
        $injector = $this->createInjector();
        $explicitProvider = $this->simpleProvider('explicit-argument');
        $result = $injector->inject(
            [Methods::class, 'staticMethodWithSimpleProviderClassParameter'],
            ['simpleProvider' => $explicitProvider]
        );

        $this->assertSame($explicitProvider, $result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithDefaultValuedParameter(): void
    {
        $injector = $this->createInjector();
        $result = $injector->inject([Methods::class, 'staticMethodWithDefaultValuedParameter']);

        $this->assertTrue($result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    #[Group(self::TEST_SCALAR_INJECTION)]
    public function testInjectStaticMethodWithExplicitNullArgument(): void
    {
        $injector = $this->createInjector();
        $result = $injector->inject([Methods::class, 'staticMethodWithNullableParameter'], ['value' => null]);

        $this->assertNull($result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    #[Group(self::TEST_INJECT_ATTRIBUTE)]
    public function testExplicitArgumentOverridesInjectAttributeForTheSameParameter(): void
    {
        $injector = $this->createInjector(['nullable' => 'from-attribute']);
        $result = $injector->inject(
            [Methods::class, 'staticMethodWithNullableParameterProvidedViaInjectParameterAttribute'],
            ['value' => 'from-arguments']
        );

        $this->assertSame('from-arguments', $result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithVariableLengthArgumentLists(): void
    {
        $injector = $this->createInjector([SimpleProvider::class => $this->simpleProvider()]);
        $result = $injector->inject([Methods::class, 'staticMethodWithVariableLengthArgumentList']);

        $this->assertSame(1, $result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithEmptyVariableLengthArgumentList(): void
    {
        $injector = $this->createInjector();
        $result = $injector->inject([Methods::class, 'staticMethodWithVariableLengthArgumentList']);

        $this->assertSame(0, $result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithMultipleExplicitVariableLengthArguments(): void
    {
        $injector = $this->createInjector();
        $result = $injector->inject(
            [Methods::class, 'staticMethodWithVariableLengthArgumentList'],
            [0 => $this->simpleProvider('first'), 1 => $this->simpleProvider('second')]
        );

        $this->assertSame(2, $result);
    }

    #[Group(self::TEST_METHOD_INJECTION)]
    public function testInjectStaticMethodWithListProviderForVariableLengthArguments(): void
    {
        $injector = $this->createInjector([
            SimpleProvider::class => [
                $this->simpleProvider('first'),
                $this->simpleProvider('second'),
            ],
        ]);
        $result = $injector->inject([Methods::class, 'staticMethodWithVariableLengthArgumentList']);

        $this->assertSame(2, $result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testGlobalFunctionCallable(): void
    {
        $injector = $this->createInjector();
        $result = $injector->inject('strlen', ['string' => 'hello']);

        $this->assertSame(5, $result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testStaticMethodCallable(): void
    {
        $this->assertCallableInjectionReturnsNull(Methods::class . '::staticVoidMethodWithoutParameters');
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testArrayFormStaticMethodCallable(): void
    {
        $this->assertCallableInjectionReturnsNull([Methods::class, 'staticVoidMethodWithoutParameters']);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testArrayFormObjectNonStaticMethodCallable(): void
    {
        $injector = $this->createInjector();
        $object = new Methods($this->simpleProvider());
        $result = $injector->inject([$object, 'staticVoidMethodWithoutParameters']);

        $this->assertNull($result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testInvalidArrayCallable(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(self::ERR_NO_VALID_INJECTION_TARGET);

        $invalidCallable = [Methods::class, true];
        $this->createInjector()->inject($invalidCallable);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testClosureCallable(): void
    {
        $injector = $this->createInjector();
        $callable = function (string $name): string {
            return "Hello, $name!";
        };
        $result = $injector->inject($callable, ['name' => 'John']);

        $this->assertSame('Hello, John!', $result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testInvokeMagicMethodCallable(): void
    {
        $injector = $this->createInjector();
        $object = new class () {
            public function __invoke(string $name): string
            {
                return "Hello, $name!";
            }
        };
        $result = $injector->inject($object, ['name' => 'John']);

        $this->assertSame('Hello, John!', $result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testCallMagicMethodArrayFormCallable(): void
    {
        $object = new MagicMethods();
        $injector = $this->createInjector();

        $result = $injector->inject(
            [$object, 'runTest'],
            ['in object context']
        );

        $this->assertSame("Calling object method 'runTest' in object context", $result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testCallMagicMethodFirstClassCallableSyntaxCallable(): void
    {
        $object = new MagicMethods();
        $injector = $this->createInjector();

        $result = $injector->inject(
            $object->runTest(...),
            ['in object context']
        );

        $this->assertSame("Calling object method 'runTest' in object context", $result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testCallStaticMagicMethodStringFormCallable(): void
    {
        $injector = $this->createInjector();

        $result = $injector->inject(
            MagicMethods::class . '::runTest',
            ['in static context']
        );

        $this->assertSame("Calling static method 'runTest' in static context", $result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testCallStaticMagicMethodArrayFormCallable(): void
    {
        $injector = $this->createInjector();

        $result = $injector->inject(
            [MagicMethods::class, 'runTest'],
            ['in static context']
        );

        $this->assertSame("Calling static method 'runTest' in static context", $result);
    }

    #[Group(self::TEST_CALLABLE_INJECTION)]
    public function testCallStaticMagicMethodFirstClassCallableSyntaxCallable(): void
    {
        $injector = $this->createInjector();

        $result = $injector->inject(
            MagicMethods::runTest(...),
            ['in static context']
        );

        $this->assertSame("Calling static method 'runTest' in static context", $result);
    }

    #[Group(self::TEST_SCALAR_INJECTION)]
    #[Group(self::TEST_INJECT_ATTRIBUTE)]
    public function testInjectScalarProvider(): void
    {
        $injector = $this->createInjector(['n1' => 1]);
        $result = $injector->inject('n1');

        $this->assertIsInt($result);
        $this->assertSame(1, $result);
    }

    #[Group(self::TEST_SCALAR_INJECTION)]
    #[Group(self::TEST_INJECT_ATTRIBUTE)]
    public function testInjectNullScalarProvider(): void
    {
        $injector = $this->createInjector(['nullable' => null]);
        $result = $injector->inject([Methods::class, 'staticMethodWithNullableParameterProvidedViaInjectParameterAttribute']);

        $this->assertNull($result);
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testInjectInvalidArrayStructureThrowsException(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(self::ERR_NO_VALID_INJECTION_TARGET);

        $invalidArrayStructure = ['class', 'method', 'extra'];
        $this->createInjector()->inject($invalidArrayStructure);
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testInjectInvalidTargetTypeThrowsException(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(self::ERR_NO_VALID_INJECTION_TARGET);

        $invalidTargetType = [42];
        $this->createInjector()->inject($invalidTargetType);
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testInjectNonInjectableIdThrowsException(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage('Non-injectable injection target provided.');

        $injector = $this->createInjector();
        $injector->inject('');
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testInjectUnknownClassStringThrowsException(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(self::ERR_NO_VALID_INJECTION_TARGET);

        $injector = $this->createInjector();
        $injector->inject('PHPInjector\\Tests\\Mock\\MissingProvider');
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testInjectStaticMethodWithoutRequiredProviderException(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(
            'No provider or value found for parameter: PHPInjector\\Tests\\Mock\\SimpleProvider $simpleProvider'
        );

        $injector = $this->createInjector();
        $injector->inject([Methods::class, 'staticMethodWithSimpleProviderClassParameter']);
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testInjectStaticMethodWithProviderWithoutRequiredProviderException(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(
            'No provider or value found for parameter: PHPInjector\\Tests\\Mock\\SimpleProvider $simpleProvider'
        );

        $injector = $this->createInjector([ProviderWithRequiredProvider::class]);
        $injector->inject([Methods::class, 'staticMethodWithProviderWithRequiredProviderClassParameter']);
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testGetReflectionMethodException(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(
            "The method 'undefinedMethod' is undefined in class 'PHPInjector\\Tests\\Mock\\Methods'."
        );

        $injector = $this->createInjector();
        $injector->inject([Methods::class, 'undefinedMethod']);
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testInjectStaticProtectedMethod(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(
            "Method 'staticProtectedMethod' of class 'PHPInjector\\Tests\\Mock\\Methods' is not accessible."
        );

        $injector = $this->createInjector([$this->simpleProvider()]);
        $injector->inject([Methods::class, 'staticProtectedMethod']);
    }

    #[Group(self::TEST_INJECTOR_EXCEPTION)]
    public function testInjectStaticPrivateMethod(): void
    {
        $this->expectException(InjectorException::class);
        $this->expectExceptionMessage(
            "Method 'staticPrivateMethod' of class 'PHPInjector\\Tests\\Mock\\Methods' is not accessible."
        );

        $injector = $this->createInjector([$this->simpleProvider()]);
        $injector->inject([Methods::class, 'staticPrivateMethod']);
    }

    /**
     * @param array<class-string|int|string, mixed> $providers
     */
    private function createInjector(array $providers = [], ?Injector $parent = null): Injector
    {
        return new Injector($providers, $parent);
    }

    private function simpleProvider(?string $providedIn = null): SimpleProvider
    {
        return new SimpleProvider($providedIn);
    }

    private function assertMethodsConsumerUsesSimpleProvider(mixed $providerConsumer): Methods
    {
        $this->assertInstanceOf(Methods::class, $providerConsumer);
        $this->assertInstanceOf(SimpleProvider::class, $providerConsumer->provider);

        /** @var Methods $providerConsumer */
        return $providerConsumer;
    }

    private function assertSingletonConsumerUsesSingletonProvider(mixed $singletonConsumer): SingletonConsumer
    {
        $this->assertInstanceOf(SingletonConsumer::class, $singletonConsumer);
        $this->assertInstanceOf(SingletonProvider::class, $singletonConsumer->singletonProvider);

        /** @var SingletonConsumer $singletonConsumer */
        return $singletonConsumer;
    }

    /**
     * @param array{0: class-string|object, 1: string}|callable|string $callable
     */
    private function assertCallableInjectionReturnsNull(array|callable|string $callable): void
    {
        $injector = $this->createInjector();

        $this->assertNull($injector->inject($callable));
    }
}
