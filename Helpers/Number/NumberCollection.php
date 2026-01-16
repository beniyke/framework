<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Provides methods to perform common number operations
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Number;

use BadMethodCallException;

class NumberCollection
{
    private $number;

    private function __construct(mixed $number)
    {
        $this->number = $number;
    }

    public static function make(mixed $number): NumberCollection
    {
        return new self($number);
    }

    public function __call(string $method, mixed $args)
    {
        if (method_exists(Number::class, $method)) {
            array_unshift($args, $this->number);
            $this->number = call_user_func_array([Number::class, $method], $args);

            return $this;
        } else {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }
    }

    public function get(): mixed
    {
        return $this->number;
    }
}
