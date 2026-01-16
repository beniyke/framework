<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This trait handles deferred or postponed tasks in the application.
 * It relies on the global 'resolve()' helper to fetch the Deferrer service,
 * avoding explicit dependency injection in the trait's consumer.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Defer;

trait DeferredTaskTrait
{
    protected function executeDeferredTasks(string $name = 'default'): void
    {
        $deferrer = resolve(DeferrerInterface::class);

        $deferrer->name($name);

        if ($deferrer->hasPayload()) {
            foreach ($deferrer->getPayloads() as $payload) {
                if (is_callable($payload)) {
                    call_user_func($payload);
                }
            }

            $deferrer->clearPayloads();
        }
    }
}
