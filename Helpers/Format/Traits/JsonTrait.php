<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This trait provides an easy and consistent way to convert an array to a JSON string
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Format\Traits;

use RuntimeException;

trait JsonTrait
{
    public static function asJson(array $data, bool $debug = false): string
    {
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $options = ! $debug ? $options : $options | JSON_PRETTY_PRINT;
        $result = json_encode($data, $options, 512);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf("Failed to parse json string, error: '%s'", json_last_error_msg()));
        }

        return $result;
    }
}
