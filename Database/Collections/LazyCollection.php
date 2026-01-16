<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * LazyCollection wrapper for memory-efficient iteration.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Database\Collections;

use Database\Query\Builder;
use IteratorAggregate;
use Traversable;

class LazyCollection implements IteratorAggregate
{
    protected Builder $query;

    protected int $chunkSize = 1000;

    protected $transformer = null;

    protected $filterer = null;

    public function __construct(Builder $query, ?callable $transformer = null, ?callable $filterer = null)
    {
        $this->query = $query;
        $this->transformer = $transformer;
        $this->filterer = $filterer;
    }

    public function getIterator(): Traversable
    {
        $query = clone $this->query;
        $offset = 0;

        do {
            $results = $query->offset($offset)->limit($this->chunkSize)->get();

            foreach ($results as $key => $item) {
                if ($this->filterer && ! call_user_func($this->filterer, $item, $key)) {
                    continue;
                }

                if ($this->transformer) {
                    $item = call_user_func($this->transformer, $item, $key);
                }

                yield $item;
            }

            $offset += $this->chunkSize;
        } while (count($results) === $this->chunkSize);
    }

    public function each(callable $callback): self
    {
        foreach ($this->getIterator() as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    public function count(): int
    {
        return $this->query->count();
    }

    public function isEmpty(): bool
    {
        return ! $this->query->limit(1)->first();
    }

    public function first(): mixed
    {
        return $this->query->first();
    }

    public function toArray(): array
    {
        $results = [];
        foreach ($this->getIterator() as $item) {
            $results[] = $item;
        }

        return $results;
    }

    public function map(callable $callback): static
    {
        $originalTransformer = $this->transformer;
        $newTransformer = function ($item, $key) use ($callback, $originalTransformer) {
            if ($originalTransformer) {
                $item = $originalTransformer($item, $key);
            }

            return $callback($item, $key);
        };

        return new static($this->query, $newTransformer, $this->filterer);
    }

    public function filter(callable $callback): static
    {
        $originalFilterer = $this->filterer;
        $newFilterer = function ($item, $key) use ($callback, $originalFilterer) {
            if ($originalFilterer && ! $originalFilterer($item, $key)) {
                return false;
            }

            return $callback($item, $key);
        };

        return new static($this->query, $this->transformer, $newFilterer);
    }
}
