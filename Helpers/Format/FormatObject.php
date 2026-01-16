<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Provides methods to perform common format operations
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Format;

use BadMethodCallException;

class FormatObject
{
    private $data;

    private function __construct(mixed $data)
    {
        $this->data = $data;
    }

    public static function make(mixed $data): self
    {
        return new self($data);
    }

    public function __call(string $method, array $args)
    {
        if (method_exists(FormatCollection::class, $method)) {
            array_unshift($args, $this->data);
            $this->data = call_user_func_array([FormatCollection::class, $method], $args);

            return $this;
        } else {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }
    }

    public function get(): mixed
    {
        return $this->data;
    }
}
