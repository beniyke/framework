<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Event Dispatcher.
 * Provides a simple observer implementation, allowing you to subscribe and listen for various events.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core;

use Core\Contracts\ShouldQueue;
use Core\Jobs\CallQueuedListener;
use Defer\Defer;
use Queue\Queue;
use Testing\Fakes\EventFake;
use Throwable;

class Event
{
    private static array $listeners = [];

    private static ?EventFake $fake = null;

    public static function fake(): EventFake
    {
        static::$fake = new EventFake();

        return static::$fake;
    }

    public static function listen(string $event, mixed $listener): void
    {
        if (! isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = $listener;
    }

    public static function deferred(object $eventInstance): void
    {
        Defer::push(function () use ($eventInstance) {
            static::dispatch($eventInstance);
        });
    }

    public static function dispatch(object $eventInstance): void
    {
        if (static::$fake) {
            static::$fake->dispatch($eventInstance);

            return;
        }

        $eventClass = get_class($eventInstance);

        if (! isset(self::$listeners[$eventClass])) {
            return;
        }

        foreach (self::$listeners[$eventClass] as $listener) {
            try {
                $instance = $listener;

                if (is_string($listener)) {
                    if (! class_exists($listener)) {
                        error_log("Event Error: Listener class '$listener' not found for event '$eventClass'.");
                        continue;
                    }
                    $instance = resolve($listener);
                }

                if ($instance instanceof ShouldQueue) {
                    Queue::dispatch(
                        CallQueuedListener::class,
                        [
                            'listener_class' => get_class($instance),
                            'event' => $eventInstance,
                        ]
                    );
                    continue;
                }

                $result = null;

                if (method_exists($instance, 'handle')) {
                    $result = $instance->handle($eventInstance);
                } elseif (is_callable($instance)) {
                    $result = $instance($eventInstance);
                } else {
                    error_log("Event Error: Listener for '$eventClass' must have a 'handle' method or be callable.");
                    continue;
                }

                if ($result === false) {
                    break;
                }
            } catch (Throwable $e) {
                error_log("Event Error: Exception in listener for '$eventClass': " . $e->getMessage());
            }
        }
    }

    public static function clearListeners(): void
    {
        self::$listeners = [];
    }

    public static function listeners(string $event): array
    {
        return self::$listeners[$event] ?? [];
    }

    public static function reset(): void
    {
        self::$fake = null;
    }
}
