<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * DeferServiceProvider registers the Deferrer service for handling deferred tasks.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Defer\Providers;

use Core\Services\DeferredServiceProvider;
use Defer\Deferrer;
use Defer\DeferrerInterface;

class DeferServiceProvider extends DeferredServiceProvider
{
    public static function provides(): array
    {
        return [
            DeferrerInterface::class,
        ];
    }

    public function register(): void
    {
        $this->container->singleton(DeferrerInterface::class, Deferrer::class);
    }
}
