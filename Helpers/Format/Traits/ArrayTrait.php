<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This trait provides a convenient way to convert array of objects,
 * including nested objects, into an array.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Format\Traits;

trait ArrayTrait
{
    public static function asArray(mixed $data, bool $recursive = false): array
    {
        if (! $recursive) {
            return (array) $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = is_object($value) ? static::asArray($value) : $value;
        }

        return $result;
    }
}
