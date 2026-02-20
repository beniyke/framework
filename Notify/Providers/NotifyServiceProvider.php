<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This service provider registers notification services and channels.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify\Providers;

use Core\Services\DeferredServiceProvider;
use Notify\NotificationManager;
use Notify\Notifier;

class NotifyServiceProvider extends DeferredServiceProvider
{
    public static function provides(): array
    {
        return [
            NotificationManager::class,
            Notifier::class,
        ];
    }

    public function register(): void
    {
        $this->container->singleton(NotificationManager::class, function () {
            return new NotificationManager();
        });
    }
}
