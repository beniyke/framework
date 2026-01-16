<?php

declare(strict_types=1);

namespace Core\Jobs;

use Helpers\File\Contracts\LoggerInterface;
use Queue\BaseTask;
use Queue\Scheduler;

class CallQueuedListener extends BaseTask
{
    public function period(Scheduler $scheduler): Scheduler
    {
        return $scheduler;
    }

    protected function execute(): bool
    {
        /** @var Data $payload */
        $payload = $this->payload;

        $listenerClass = $payload->get('listener_class');
        $event = $payload->get('event');

        if (! class_exists($listenerClass)) {
            resolve(LoggerInterface::class)->warning("Queued listener class not found: {$listenerClass}");

            return false;
        }

        $listener = resolve($listenerClass);

        if (method_exists($listener, 'handle')) {
            $listener->handle($event);
        } elseif (is_callable($listener)) {
            $listener($event);
        }

        return true;
    }

    protected function successMessage(): string
    {
        return 'Queued listener executed successfully';
    }

    protected function failedMessage(): string
    {
        return 'Queued listener execution failed';
    }
}
