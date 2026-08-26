<?php

declare(strict_types=1);

namespace PHPInjector\Container;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Psr\Container\ContainerInterface;
use function array_key_exists;
use function count;
use function is_bool;
use function is_object;
use function is_string;
use function settype;

/**
 * Small PSR-11 provider store used by the injector.
 *
 * String keys are kept as supplied. For list-style entries, a string value
 * becomes the identifier and an object value uses its class name as the
 * identifier. Normalization happens only in the constructor; set() replaces
 * entries by their explicit string identifier.
 *
 * @template T of mixed
 * @implements IteratorAggregate<class-string|int|string, mixed>
 */
class Container implements ContainerInterface, Countable, IteratorAggregate
{
    /**
     * Create a container and normalize any list-style entries.
     *
     * @param array<class-string|int|string, mixed> $content
     * @throws ContainerException When a list entry cannot produce an identifier
     *     or when normalized identifiers are duplicated.
     */
    public function __construct(private(set) array $content = [])
    {
        $processedContent = [];

        foreach ($content as $key => $value) {
            $keyStr = $this->resolveContentKey($key, $value);

            if (array_key_exists($keyStr, $processedContent)) {
                throw new ContainerException("Duplicate key: '$keyStr'");
            }

            $processedContent[$keyStr] = $value;
        }

        $this->content = $processedContent;
    }

    /**
     * Resolve the identifier for one constructor entry.
     */
    private function resolveContentKey(int|string $key, mixed $value): string
    {
        if (is_string($key)) {
            return $key;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_object($value)) {
            return $value::class;
        }

        throw new ContainerException(
            "Invalid key($key) => value(" . $this->stringifyInvalidValue($value) . ').'
        );
    }

    /**
     * Keep invalid list-entry errors readable without changing the original value.
     */
    private function stringifyInvalidValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        settype($value, 'string');

        return $value;
    }

    /**
     * Check whether an identifier is present, including entries whose value is null.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->content);
    }

    /**
     * Store or replace a provider under an explicit identifier.
     */
    public function set(string $id, mixed $value): void
    {
        $this->content[$id] = $value;
    }

    /**
     * Remove an identifier. Missing identifiers are ignored.
     */
    public function unset(string $id): void
    {
        unset($this->content[$id]);
    }

    /**
     * Return a provider by identifier.
     *
     * @throws NotFoundException When the identifier is not present.
     */
    public function get(string $id): mixed
    {
        if ($this->has($id)) {
            return $this->content[$id];
        }

        throw new NotFoundException("No entry was found for $id identifier.");
    }

    /**
     * Return the number of stored identifiers.
     */
    public function count(): int
    {
        return count($this->content);
    }

    /**
        * Iterate over identifiers and their stored providers.
        *
     * @return ArrayIterator<int|string, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->content);
    }
}
