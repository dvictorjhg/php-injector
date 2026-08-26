<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Unit;

use PHPInjector\Tests\Mock\SingletonProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(SingletonProvider::class)]
#[RunTestsInSeparateProcesses]
final class SingletonProviderTest extends TestCase
{
    public function testSingletonContractEncapsulatesConstructionWithPrivateConstructor(): void
    {
        // This fixture hides its constructor so callers must use getInstance() for construction.
        $reflectionClass = new ReflectionClass(SingletonProvider::class);
        $constructor = $reflectionClass->getConstructor();
        $getInstance = $reflectionClass->getMethod('getInstance');

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate());
        $this->assertTrue($getInstance->isPublic());
        $this->assertTrue($getInstance->isStatic());
    }

    public function testSingletonContractCreatesAndOwnsInstanceInternallyOnFirstAccess(): void
    {
        // This implementation constructs the instance and sets its default state on first access.
        $instance = SingletonProvider::getInstance();

        $this->assertSame('A', SingletonProvider::A);
        $this->assertSame('B', $instance->instanceValue);
    }

    public function testSingletonContractAlwaysReturnsSameInternallyManagedInstance(): void
    {
        // This implementation returns the same object from every getInstance() call, so state
        // mutations are visible through any reference.
        $firstInstance = SingletonProvider::getInstance();
        $firstInstance->instanceValue = 'shared-state';

        $secondInstance = SingletonProvider::getInstance();

        $this->assertSame($firstInstance, $secondInstance);
        $this->assertSame('shared-state', $secondInstance->instanceValue);
    }
}
