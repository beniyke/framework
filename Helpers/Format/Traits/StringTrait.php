<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This trait provides a convenient way to convert various types of data to a string format.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Format\Traits;

trait StringTrait
{
    public static function asString(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data) || is_object($data)) {
            if (is_object($data)) {
                $data = static::asArray($data, true);
            }

            $result = [];

            foreach ($data as $key => $value) {
                $result[] = $key . ': ' . $value . ';';
            }

            return implode(PHP_EOL, $result);
        }
    }
}
