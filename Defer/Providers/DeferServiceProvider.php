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

use Core\Contracts\TerminableInterface;
use Core\Services\DeferredServiceProvider;
use Defer\Deferrer;
use Defer\DeferrerInterface;

class DeferServiceProvider extends DeferredServiceProvider implements TerminableInterface
{
    public function terminate(): void
    {
        if ($this->container->has(DeferrerInterface::class)) {
            $this->container->get(DeferrerInterface::class)->terminate();
        }
    }

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
