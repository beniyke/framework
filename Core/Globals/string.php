<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * String helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Helpers\String\StrCollection;
use Helpers\String\Text;
use Helpers\String\TextCollection;

if (! function_exists('str')) {
    function str(mixed $string): StrCollection
    {
        return StrCollection::make($string);
    }
}

if (! function_exists('text')) {
    function text(string $text): TextCollection
    {
        return TextCollection::make($text);
    }
}

if (! function_exists('plural')) {
    function plural(string $value): string
    {
        return Text::pluralize($value);
    }
}

if (! function_exists('inflect')) {
    function inflect(string $value, int $count): string
    {
        return Text::inflect($value, $count);
    }
}
