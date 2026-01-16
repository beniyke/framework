<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Abstract class for service providers that are deferred until needed.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

abstract class DeferredServiceProvider extends ServiceProvider
{
    abstract public static function provides(): array;
}
