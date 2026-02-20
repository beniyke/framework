<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Defer Facade
 * Provides a static interface for task deferral.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Defer;

class Defer
{
    /**
     * Set the named scope for the deferrer.
     *
     * @param string $name
     *
     * @return DeferrerInterface
     */
    public static function name(string $name): DeferrerInterface
    {
        return resolve(DeferrerInterface::class)->name($name);
    }

    /**
     * Push a task to the default deferrer.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function push(callable $callback): void
    {
        resolve(DeferrerInterface::class)->name('default')->push($callback);
    }
}
