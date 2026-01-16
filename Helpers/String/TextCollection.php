<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * TextCollection provides methods to perform common text operations
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\String;

use BadMethodCallException;

class TextCollection
{
    private string $string;

    private function __construct(string $text)
    {
        $this->string = $text;
    }

    public static function make(string $text): TextCollection
    {
        return new self($text);
    }

    public function __call(string $method, mixed $args)
    {
        if (method_exists(Text::class, $method)) {
            array_unshift($args, $this->string);
            $this->string = call_user_func_array([Text::class, $method], $args);

            return $this;
        } else {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }
    }

    public function get(): string
    {
        return $this->string;
    }
}
