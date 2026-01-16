<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Array helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Helpers\Array\ArrayCollection;
use Helpers\Array\Collections;

if (! function_exists('arr')) {
    function arr(mixed $collection = null): Collections|ArrayCollection
    {
        return $collection === null
            ? new ArrayCollection()
            : new Collections($collection);
    }
}
