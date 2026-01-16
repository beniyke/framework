<?php

declare(strict_types=1);

namespace Testing\Fakes;

use PHPUnit\Framework\Assert as PHPUnit;

class EventFake
{
    /**
     * All of the dispatched events.
     */
    protected array $events = [];

    public function dispatch(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Assert if an event was dispatched based on a truth-test callback.
     */
    public function assertDispatched(string $event, $callback = null): void
    {
        $dispatchedCount = count(array_filter($this->events, function ($e) use ($event, $callback) {
            if (! $e instanceof $event) {
                return false;
            }

            return $callback ? $callback($e) : true;
        }));

        PHPUnit::assertTrue(
            $dispatchedCount > 0,
            "The expected [{$event}] event was not dispatched."
        );
    }

    /**
     * Assert if an event was not dispatched.
     */
    public function assertNotDispatched(string $event, $callback = null): void
    {
        $dispatchedCount = count(array_filter($this->events, function ($e) use ($event, $callback) {
            if (! $e instanceof $event) {
                return false;
            }

            return $callback ? $callback($e) : true;
        }));

        PHPUnit::assertEquals(
            0,
            $dispatchedCount,
            "The unexpected [{$event}] event was dispatched."
        );
    }

    /**
     * Assert that no events were dispatched.
     */
    public function assertNothingDispatched(): void
    {
        PHPUnit::assertEmpty($this->events, 'Events were dispatched unexpectedly.');
    }
}
