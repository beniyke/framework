<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The Macroable trait allows classes to be dynamically extended with new methods at runtime.
 * It provides methods to register and call custom macros.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers;

use BadMethodCallException;
use Closure;

trait Macroable
{
    protected static array $macros = [];

    public static function macro(string $name, callable $macro): void
    {
        static::$macros[static::class][$name] = $macro;
    }

    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[static::class][$name]);
    }

    public static function flushMacros(): void
    {
        unset(static::$macros[static::class]);
    }

    public function __call($method, $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }

        $macro = static::$macros[static::class][$method];

        if ($macro instanceof Closure) {
            $macro = $macro->bindTo($this, static::class);
        }

        return $macro(...$parameters);
    }

    public static function __callStatic($method, $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }

        $macro = static::$macros[static::class][$method];

        if ($macro instanceof Closure) {
            $macro = $macro->bindTo(null, static::class);
        }

        return $macro(...$parameters);
    }
}
