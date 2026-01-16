<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides a simple implementation of a collection class that allows for
 * easy retrieval of values by key and access to the entire collection.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Http;

use Helpers\Array\ArrayCollection;

class Collection
{
    protected $parameter;

    public function __construct(array $parameter = [])
    {
        $this->parameter = $parameter;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = ArrayCollection::value($this->parameter, $key, $default);

        if (is_numeric($value)) {
            $value += 0;
        }

        return $value ?? $default;
    }

    public function all(): array
    {
        return $this->parameter;
    }
}
