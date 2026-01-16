<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Validation helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Helpers\Data;
use Helpers\Validation\Validator;

if (! function_exists('data')) {
    function data(array $payload): Data
    {
        return Data::make($payload);
    }
}

if (! function_exists('validator')) {
    function validator(): Validator
    {
        return new Validator();
    }
}
