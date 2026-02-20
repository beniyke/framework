<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The Mixins trait allows classes to be dynamically extended with multiple methods at once.
 * It provides a way to "mix in" methods from another class or object.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

trait Mixins
{
    /**
     * Mix another object into the class.
     *
     * @param object|string $mixin
     * @param bool          $replace
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public static function mixin(object|string $mixin, bool $replace = true): void
    {
        $instance = is_string($mixin) ? new $mixin() : $mixin;
        $methods = (new ReflectionClass($instance))->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            if (!$replace && static::hasMacro($method->name)) {
                continue;
            }

            $method->setAccessible(true);
            $macro = $method->invoke($instance);

            if (is_callable($macro)) {
                static::macro($method->name, $macro);
            }
        }
    }
}
