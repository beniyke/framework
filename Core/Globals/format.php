<?php

/**
 * Anchor Framework
 *
 * Formatting helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

declare(strict_types=1);

use Helpers\Format\FormatCollection;
use Helpers\Format\FormatObject;
use Helpers\Number\Number;
use Helpers\Number\NumberCollection;

if (! function_exists('number')) {
    function number(?int $num = null): Number|NumberCollection
    {
        return $num === null
            ? new Number()
            : NumberCollection::make($num);
    }
}

if (! function_exists('format')) {
    function format(mixed $data = null): FormatObject|FormatCollection
    {
        return empty($data)
            ? new FormatCollection()
            : FormatObject::make($data);
    }
}
