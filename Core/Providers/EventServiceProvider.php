<?php

declare(strict_types=1);

namespace Core\Providers;

use Core\Event;
use Core\Events\ConsoleTerminateEvent;
use Core\Events\KernelTerminateEvent;
use Core\Events\Listeners\ClearResourceCacheListener;
use Core\Events\Listeners\ProcessDeferredTasksListener;
use Core\Services\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    /**
     * The event listener mappings for the application.
     *
     * @var array<string, array<string>>
     */
    protected array $listen = [
        KernelTerminateEvent::class => [
            ClearResourceCacheListener::class,
            ProcessDeferredTasksListener::class,
        ],
        ConsoleTerminateEvent::class => [
            ProcessDeferredTasksListener::class,
        ],
    ];

    public function boot(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
