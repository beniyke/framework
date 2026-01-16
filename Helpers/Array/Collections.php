<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Collections provides a fluent, object-oriented wrapper (Collection)
 * for performing common array operations, leveraging the methods
 * of the static ArrayCollection utility class via the __call magic method.
 *
 * It implements ArrayAccess, Countable, and IteratorAggregate for array-like behavior.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Array;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Countable;
use Helpers\Format\FormatObject;
use IteratorAggregate;
use RuntimeException;
use Traversable;

/**
 * @mixin ArrayCollection
 */
class Collections implements ArrayAccess, Countable, IteratorAggregate
{
    protected mixed $items;

    public function __construct(array $array = [])
    {
        $this->items = $array;
    }

    public static function make(mixed $array = []): self
    {
        if ($array instanceof self) {
            $array = $array->get();
        }

        return new self((array) $array);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        $utility = ArrayCollection::class;

        if (! method_exists($utility, $method)) {
            throw new BadMethodCallException("Static method {$method} does not exist on " . self::class . " or {$utility}.");
        }

        return call_user_func_array([$utility, $method], $args);
    }

    public function __call(string $method, array $args): mixed
    {
        $utility = ArrayCollection::class;

        if (! method_exists($utility, $method)) {
            throw new BadMethodCallException("Method {$method} does not exist on " . self::class . " or {$utility}.");
        }

        array_unshift($args, $this->items);

        $result = call_user_func_array([$utility, $method], $args);

        $non_fluent_methods = [
            'value',
            'first',
            'last',
            'max',
            'min',
            'firstKey',
            'lastKey',
            'shift',
            'pop',
            'sum',
            'avg',
            'mean',
            'median',
            'variance',
            'stdDev',
            'has',
            'exists',
            'hasAll',
            'reduce',
            'contains',
            'count',
            'isEmpty',
            'isAssoc',
            'isArrayOfArrays',
            'isMultiDimensional',
            'isEqual',
            'partition',
            'random',
            'zip',
            'getKeys',
            'mode',
            'only',
            'exclude',
            'safeImplode',
        ];

        if (in_array($method, $non_fluent_methods, true)) {
            return $result;
        }

        $this->items = (array) $result;

        return $this;
    }

    public function get(): mixed
    {
        return $this->items;
    }

    public function all(): FormatObject
    {
        return FormatObject::make($this->items);
    }

    public function reset(array $items = []): self
    {
        $this->items = $items;

        return $this;
    }

    /**
     * Set a value at a specific key in the collection.
     */
    public function put(string $key, mixed $value): self
    {
        $this->items[$key] = $value;

        return $this;
    }

    public function custom(callable $function): self
    {
        $this->items = $function($this->items);

        if (! is_array($this->items)) {
            throw new RuntimeException('Custom callable must return an array to maintain collection state.');
        }

        return $this;
    }

    public function count(): int
    {
        return ArrayCollection::count((array) $this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return ArrayCollection::has((array) $this->items, (string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
