<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Manages the queuing of jobs and their payloads.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue;

use Core\Services\ConfigServiceInterface;
use Helpers\DateTimeHelper;
use Queue\Interfaces\Queueable;
use Queue\Models\QueuedJob;
use RuntimeException;

class QueueManager
{
    private string $identifier = 'default';

    private ?string $payload = null;

    private ?DateTimeHelper $schedule = null;

    private ?Queueable $taskInstance = null;

    private ConfigServiceInterface $config;

    private Scheduler $scheduler;

    public function __construct(ConfigServiceInterface $config, Scheduler $scheduler)
    {
        $this->config = $config;
        $this->scheduler = $scheduler;
    }

    public function identifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function job(string $namespace, mixed $data): self
    {
        if (! class_exists($namespace)) {
            throw new RuntimeException('Class does not exist: ' . $namespace);
        }

        $task = new $namespace();

        if (! $task instanceof Queueable) {
            throw new RuntimeException('Job class must implement Queueable: ' . $namespace);
        }

        $this->taskInstance = $task;

        // Use serialize for full object support
        $serializedData = serialize(is_object($data) ? clone $data : $data);
        if ($serializedData === false) {
            throw new RuntimeException('Failed to serialize task data.');
        }

        $jobScheduler = clone $this->scheduler;

        if (method_exists($task, 'period')) {
            $jobScheduler = $task->period($jobScheduler);
        }

        $this->schedule = $jobScheduler->time();

        $this->payload = json_encode([
            'namespace' => $namespace,
            'data' => $serializedData,
        ]);

        if ($this->payload === false) {
            throw new RuntimeException('Failed to encode payload as JSON.');
        }

        return $this;
    }

    public function queue(): QueuedJob
    {
        if (! $this->payload) {
            throw new RuntimeException('Job payload has not been set. Call job() before queue().');
        }

        $schedule = $this->schedule ?? DateTimeHelper::now();

        $queuedJob = QueuedJob::queue($this->identifier, $this->payload, $schedule);

        $this->identifier = 'default';
        $this->payload = null;
        $this->schedule = null;
        $this->taskInstance = null;

        return $queuedJob;
    }

    public function defer(): void
    {
        defer(function () {
            $this->queue();
        });
    }
}
