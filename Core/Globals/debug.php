<?php

/**
 * Anchor Framework
 *
 * Debugging helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

declare(strict_types=1);

use Helpers\File\FileLogger;
use Helpers\VarDump;

if (!function_exists('dd')) {
    function dd(): void
    {
        $args = func_get_args();
        (new VarDump())->dd(count($args) === 1 ? $args[0] : $args);
    }
}

if (!function_exists('dump')) {
    function dump(): void
    {
        $args = func_get_args();
        (new VarDump())->dump(count($args) === 1 ? $args[0] : $args);
    }
}

if (!function_exists('logger')) {
    function logger(string $file): FileLogger
    {
        return new FileLogger($file);
    }
}

if (!function_exists('benchmark')) {
    /**
     * Measure the execution time of a callback or return the Benchmark class.
     */
    function benchmark(string $key, ?callable $callback = null): mixed
    {
        if ($callback === null) {
            return Helpers\Benchmark::class;
        }

        return Helpers\Benchmark::measure($key, $callback);
    }
}
