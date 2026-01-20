<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class provides methods to perform common string operations
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\String;

use BadMethodCallException;

/**
 * @method self contains(string|array $needles, bool $ignoreCase = false)
 * @method self startsWith(string $needle, bool $ignoreCase = false)
 * @method self endsWith(string $needle, bool $ignoreCase = false)
 * @method self limit(int $limit = 100, string $end = '...')
 * @method self lower()
 * @method self upper()
 * @method self trim()
 * @method self replace(array|string $find, array|string $replace)
 * @method self prettyImplode(string $conjunction = 'and')
 */
class StrCollection
{
    private $string;

    private function __construct(mixed $string)
    {
        $this->string = $string;
    }

    public static function make(mixed $string): StrCollection
    {
        return new self($string);
    }

    public function __call(string $method, mixed $args)
    {
        if (method_exists(Str::class, $method)) {
            array_unshift($args, $this->string);
            $this->string = call_user_func_array([Str::class, $method], $args);

            return $this;
        } else {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }
    }

    public function get(): mixed
    {
        return $this->string;
    }
}
