<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This trait provides a convenient way to convert arrays to objects.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Format\Traits;

trait ObjectTrait
{
    public static function asObject(array $data, bool $recursive = false): object
    {
        if (! $recursive) {
            return (object) $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = is_array($value) ? static::asObject($value) : $value;
        }

        return static::asObject($result);
    }
}
