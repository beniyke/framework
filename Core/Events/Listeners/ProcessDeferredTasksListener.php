<?php

declare(strict_types=1);

namespace Core\Events\Listeners;

use Core\Events\ConsoleTerminateEvent;
use Core\Events\KernelTerminateEvent;
use Defer\DeferrerInterface;
use Helpers\Log;
use Throwable;

class ProcessDeferredTasksListener
{
    /**
 * Anchor Framework
 *
 * Create a new listener instance.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
    public function __construct(
        protected DeferrerInterface $deferrer
    ) {
    }

    public function handle(KernelTerminateEvent|ConsoleTerminateEvent $event): void
    {
        $this->executeDeferredTasks();
    }

    /**
     * Execute all deferred tasks.
     */
    protected function executeDeferredTasks(string $name = 'default'): void
    {
        $this->deferrer->name($name);

        if ($this->deferrer->hasPayload()) {
            foreach ($this->deferrer->getPayloads() as $payload) {
                if (is_callable($payload)) {
                    try {
                        call_user_func($payload);
                    } catch (Throwable $e) {
                        Log::channel('error')->error('Deferred task failed: ' . $e->getMessage(), [
                            'exception' => $e,
                            'payload' => $payload
                        ]);
                    }
                }
            }

            $this->deferrer->clearPayloads();
        }
    }
}
