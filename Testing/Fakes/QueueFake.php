<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake QueueManager for testing job dispatching.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use PHPUnit\Framework\Assert as PHPUnit;

class QueueFake
{
    /**
     * All of the jobs that have been pushed.
     *
     * @var array<int, array{job: string, data: mixed, queue: string}>
     */
    protected array $jobs = [];

    /**
     * The current queue identifier.
     */
    protected string $identifier = 'default';

    /**
     * The pending job namespace.
     */
    protected ?string $pendingJob = null;

    /**
     * The pending job data.
     */
    protected mixed $pendingData = null;

    public function identifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Specify a job to be pushed.
     */
    public function job(string $namespace, mixed $data): self
    {
        $this->pendingJob = $namespace;
        $this->pendingData = $data;

        return $this;
    }

    /**
     * Push the job to the fake queue.
     */
    public function queue(): self
    {
        if ($this->pendingJob === null) {
            return $this;
        }

        $this->jobs[] = [
            'job' => $this->pendingJob,
            'data' => $this->pendingData,
            'queue' => $this->identifier,
        ];

        $this->pendingJob = null;
        $this->pendingData = null;
        $this->identifier = 'default';

        return $this;
    }

    /**
     * Push a job directly (shorthand).
     */
    public function push(string $job, mixed $data = null, string $queue = 'default'): self
    {
        $this->jobs[] = [
            'job' => $job,
            'data' => $data,
            'queue' => $queue,
        ];

        return $this;
    }

    /**
     * Assert if a job was pushed.
     */
    public function assertPushed(string $job, ?callable $callback = null): void
    {
        $count = $this->pushed($job, $callback);

        PHPUnit::assertTrue(
            count($count) > 0,
            "The expected [{$job}] job was not pushed."
        );
    }

    /**
     * Assert if a job was pushed to a specific queue.
     */
    public function assertPushedOn(string $queue, string $job, ?callable $callback = null): void
    {
        $this->assertPushed($job, function ($data, $pushedQueue) use ($queue, $callback) {
            if ($pushedQueue !== $queue) {
                return false;
            }

            return $callback ? $callback($data, $pushedQueue) : true;
        });
    }

    /**
     * Assert job was pushed a specific number of times.
     */
    public function assertPushedTimes(string $job, int $times): void
    {
        $count = count($this->pushed($job));

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$job}] job was pushed {$count} times instead of {$times} times."
        );
    }

    /**
     * Assert that a job was not pushed.
     */
    public function assertNotPushed(string $job, ?callable $callback = null): void
    {
        $count = $this->pushed($job, $callback);

        PHPUnit::assertCount(
            0,
            $count,
            "The unexpected [{$job}] job was pushed."
        );
    }

    /**
     * Assert that no jobs were pushed.
     */
    public function assertNothingPushed(): void
    {
        PHPUnit::assertEmpty(
            $this->jobs,
            'Jobs were pushed unexpectedly.'
        );
    }

    /**
     * Get all matching pushed jobs.
     *
     * @return array<int, array{job: string, data: mixed, queue: string}>
     */
    public function pushed(string $job, ?callable $callback = null): array
    {
        return array_filter($this->jobs, function ($pushed) use ($job, $callback) {
            if ($pushed['job'] !== $job) {
                return false;
            }

            return $callback ? $callback($pushed['data'], $pushed['queue']) : true;
        });
    }

    /**
     * Get all pushed jobs.
     *
     * @return array<int, array{job: string, data: mixed, queue: string}>
     */
    public function all(): array
    {
        return $this->jobs;
    }

    public function clear(): void
    {
        $this->jobs = [];
    }
}
