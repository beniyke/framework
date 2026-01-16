<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The Data class is a wrapper around the Collections class, providing an array-access interface
 * for manipulating and retrieving data sets with dot-notation support and method chaining.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers;

use ArrayAccess;
use BadMethodCallException;
use Helpers\Array\Collections;
use InvalidArgumentException;

class Data implements ArrayAccess
{
    private Collections $data;

    public function __construct(Collections $data)
    {
        $this->data = $data;
    }

    public static function make(array $data, ?array $only = null): self
    {
        if ($only === null) {
            $only = array_keys($data);
        }

        $collection = Collections::make($data);
        $filteredCollection = $collection->only($only);

        if (! ($filteredCollection instanceof Collections)) {
            $filteredCollection = Collections::make((array) $filteredCollection);
        }

        return new self($filteredCollection);
    }

    public function add(array $items): self
    {
        return $this->transform($this->data->attach($items));
    }

    public function remove(array $items): self
    {
        return $this->transform($this->data->exclude($items));
    }

    public function update(array $items): self
    {
        return $this->transform($this->data->replaceValues($items));
    }

    public function select(array $items): self
    {
        return $this->transform($this->data->only($items));
    }

    private function transform(Collections|array $result): self
    {
        if ($result instanceof Collections) {
            $result = $result->get();
        }

        return static::make($result);
    }

    public function has(string|array $items): bool
    {
        if (is_string($items)) {
            $items = [$items];
        }

        return $this->data->hasAll($items);
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function filled(array $items): bool
    {
        foreach ($items as $item) {
            $value = $this->get((string) $item);
            if (! isset($value) || $value === '') {
                return false;
            }
        }

        return true;
    }

    public function get(string|int $key, mixed $default = null): mixed
    {
        return $this->data->hasAll([$key]) ? $this->data->value($key) : $default;
    }

    public function only(array $items): array
    {
        return $this->data->only($items);
    }

    public function data(): array
    {
        return array_map(function ($value) {
            if (is_string($value) && trim($value) === '') {
                return null;
            }

            return $value;
        }, $this->data->get());
    }

    public function offsetExists(mixed $offset): bool
    {
        if (! is_string($offset) && ! is_int($offset)) {
            throw new InvalidArgumentException('Offset must be a string or integer.');
        }

        return $this->data->hasAll([$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (! is_string($offset) && ! is_int($offset)) {
            throw new InvalidArgumentException('Offset must be a string or integer.');
        }

        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Cannot set value directly using array access. Use Data::update() or Data::add() for modification.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Cannot unset value directly using array access. Use Data::remove() for modification.');
    }
}
