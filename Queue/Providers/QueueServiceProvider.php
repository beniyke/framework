<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Service provider for the Queue system.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Providers;

use Core\Services\DeferredServiceProvider;
use Queue\Interfaces\JobServiceInterface;
use Queue\Interfaces\QueueDispatcherInterface;
use Queue\QueueDispatcher;
use Queue\Services\JobService;

class QueueServiceProvider extends DeferredServiceProvider
{
    public static function provides(): array
    {
        return [
            QueueDispatcherInterface::class,
            JobServiceInterface::class,
        ];
    }

    public function register(): void
    {
        $this->container->singleton(QueueDispatcherInterface::class, QueueDispatcher::class);
        $this->container->singleton(JobServiceInterface::class, JobService::class);
    }
}
