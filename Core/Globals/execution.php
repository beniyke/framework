<?php

/**
 * Anchor Framework
 *
 * Execution time helper functions.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

declare(strict_types=1);

use Cli\Helpers\DockCommand;
use Defer\DeferrerInterface;
use Queue\Interfaces\QueueDispatcherInterface;
use Queue\Models\QueuedJob;
use Queue\QueueManager;

if (! function_exists('queue')) {
    function queue(string $namespace, mixed $payload, string $identifier = 'default'): QueuedJob
    {
        return resolve(QueueManager::class)
            ->identifier($identifier)
            ->job($namespace, $payload)
            ->queue();
    }
}

if (! function_exists('job')) {
    function job(): QueueDispatcherInterface
    {
        return resolve(QueueDispatcherInterface::class);
    }
}

if (! function_exists('deferrer')) {
    function deferrer(): DeferrerInterface
    {
        return resolve(DeferrerInterface::class);
    }
}

if (! function_exists('defer')) {
    function defer(mixed $callbacks): void
    {
        defer_as('default', $callbacks);
    }
}

if (! function_exists('defer_as')) {
    function defer_as(string $name, callable ...$callbacks): void
    {
        $deferrer = deferrer()->name($name);
        foreach ($callbacks as $callback) {
            $deferrer->push($callback);
        }
    }
}

if (! function_exists('dock')) {
    function dock(?string $command = null): DockCommand
    {
        return $command ? DockCommand::make($command) : new DockCommand();
    }
}
