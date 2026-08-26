<?php

declare(strict_types=1);

namespace PHPInjector\Tests\Unit;

use PHPInjector\Container\Container;
use PHPInjector\Container\ContainerException;
use PHPInjector\Container\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    public function testInstantiateEmptyContainer(): void
    {
        $container = new Container();

        $this->assertInstanceOf(Container::class, $container);
        $this->assertCount(0, $container);
    }

    public function testInstantiateContainerWithSequentialStringValueUsesValueAsKey(): void
    {
        $value = 'value';
        $container = new Container([$value]);

        $this->assertInstanceOf(Container::class, $container);
        $this->assertCount(1, $container);
        $this->assertSame($value, $container->get($value));
    }

    public function testInstantiateContainerWithSequentialObjectValueUsesClassNameAsKey(): void
    {
        $value = new \stdClass();
        $container = new Container([$value]);

        $this->assertInstanceOf(Container::class, $container);
        $this->assertCount(1, $container);
        $this->assertSame($value, $container->get(\stdClass::class));
    }

    public function testInstantiateContainerWithStringKeyAndNullValue(): void
    {
        $key = 'key';
        $container = new Container([$key => null]);

        $this->assertInstanceOf(Container::class, $container);
        $this->assertCount(1, $container);
        $this->assertNull($container->get($key));
    }

    public function testInstantiateContainerWithStringKeyAndNonNullValue(): void
    {
        $key = 'key';
        $value = new \stdClass();
        $container = new Container([$key => $value]);

        $this->assertInstanceOf(Container::class, $container);
        $this->assertCount(1, $container);
        $this->assertSame($value, $container->get($key));
    }

    #[DataProvider('invalidSequentialValuesProvider')]
    public function testInstantiateContainerRejectsInvalidSequentialValues(mixed $value, string $expectedMessage): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage($expectedMessage);

        self::assertInstanceOf(Container::class, new Container([$value]));
    }

    public function testInstantiateContainerWithDuplicateKeyException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Duplicate key: 'stdClass'");

        self::assertInstanceOf(Container::class, new Container([new \stdClass(), 'stdClass']));
    }

    public function testSetStoresEntriesInContainer(): void
    {
        $container = new Container();
        $container->set('key0', 'value0');
        $container->set('key1', 'value1');

        $this->assertCount(2, $container);
        $this->assertSame('value0', $container->get('key0'));
        $this->assertSame('value1', $container->get('key1'));
    }

    public function testHasReturnsTrueForStoredKeys(): void
    {
        $container = $this->createFilledContainer();

        $this->assertTrue($container->has('key0'));
    }

    public function testHasReturnsFalseForMissingKeys(): void
    {
        $container = $this->createFilledContainer();

        $this->assertFalse($container->has('missing'));
    }

    public function testUnsetRemovesStoredKey(): void
    {
        $container = $this->createFilledContainer();
        $container->unset('key0');

        $this->assertCount(1, $container);
        $this->assertFalse($container->has('key0'));
        $this->assertSame('value1', $container->get('key1'));
    }

    public function testGetReturnsStoredValue(): void
    {
        $container = $this->createFilledContainer();

        $this->assertSame('value1', $container->get('key1'));
    }

    public function testGetIteratorReturnsContainerContents(): void
    {
        $container = $this->createFilledContainer();
        $items = iterator_to_array($container);

        $this->assertSame([
            'key0' => 'value0',
            'key1' => 'value1',
        ], $items);
    }

    public function testGetThrowsWhenKeyIsMissing(): void
    {
        $container = new Container();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No entry was found for key0 identifier.');

        $container->get('key0');
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function invalidSequentialValuesProvider(): iterable
    {
        yield 'null value' => [null, 'Invalid key(0) => value(null).'];
        yield 'bool value' => [true, 'Invalid key(0) => value(true).'];
        yield 'int value' => [1, 'Invalid key(0) => value(1).'];
    }

    /**
     * @return Container<mixed>
     */
    private function createFilledContainer(): Container
    {
        return new Container([
            'key0' => 'value0',
            'key1' => 'value1',
        ]);
    }
}
