<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Queue Facade
 * Provides a static interface for dispatching jobs.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue;

use Queue\Models\QueuedJob;

class Queue
{
    /**
     * Dispatch a job to the queue.
     *
     * @param string $jobClass The class name of the job/task.
     * @param mixed  $data     The data to pass to the job.
     * @param string $queue    The queue identifier (default: 'default').
     *
     * @return QueuedJob
     */
    public static function dispatch(string $jobClass, mixed $data = [], string $queue = 'default'): QueuedJob
    {
        return resolve(QueueManager::class)
            ->identifier($queue)
            ->job($jobClass, $data)
            ->queue();
    }

    /**
     * Dispatch a job to the queue, deferred until after the response is sent.
     *
     * @param string $jobClass The class name of the job/task.
     * @param mixed  $data     The data to pass to the job.
     * @param string $queue    The queue identifier (default: 'default').
     *
     * @return void
     */
    public static function deferred(string $jobClass, mixed $data = [], string $queue = 'default'): void
    {
        resolve(QueueManager::class)
            ->identifier($queue)
            ->job($jobClass, $data)
            ->defer();
    }
}
