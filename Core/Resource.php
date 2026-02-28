<?php

declare(strict_types=1);

namespace Core;

use JsonSerializable;

abstract class Resource implements JsonSerializable
{
    protected $data;

    public function __construct(mixed $data)
    {
        $this->data = $data;
    }

    abstract public function toArray(): array;

    /**
     * Convert the resource to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public static function collection(array $resources): array
    {
        return array_map(function ($resource) {
            return (new static($resource))->toArray();
        }, $resources);
    }

    /**
     * Specify data which should be serialized to JSON.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
