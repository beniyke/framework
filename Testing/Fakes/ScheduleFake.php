<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake scheduler for testing cron and task scheduling.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Cron\Interfaces\CronInterface;
use Cron\Task;
use PHPUnit\Framework\Assert as PHPUnit;

class ScheduleFake implements CronInterface
{
    /**
     * All of the tasks that have been scheduled.
     *
     * @var Task[]
     */
    protected array $tasks = [];

    public function command(string $signature): Task
    {
        $task = new Task($signature);
        $this->tasks[] = $task;

        return $task;
    }

    public function run(): void
    {
        // Do nothing in fake
    }

    /**
     * Assert if a command was scheduled.
     */
    public function assertScheduled(string $signature, ?callable $callback = null): void
    {
        $scheduledCount = count(array_filter($this->tasks, function ($t) use ($signature, $callback) {
            if ($t->getSignature() !== $signature) {
                return false;
            }

            return $callback ? $callback($t) : true;
        }));

        PHPUnit::assertTrue(
            $scheduledCount > 0,
            "The expected command [{$signature}] was not scheduled."
        );
    }

    /**
     * Assert if a callback task was scheduled.
     */
    public function assertCallbackScheduled(?callable $callback = null): void
    {
        $scheduledCount = count(array_filter($this->tasks, function ($t) use ($callback) {
            if ($t->getCallback() === null) {
                return false;
            }

            return $callback ? $callback($t) : true;
        }));

        PHPUnit::assertTrue(
            $scheduledCount > 0,
            'The expected callback task was not scheduled.'
        );
    }

    /**
     * Assert that nothing was scheduled.
     */
    public function assertNothingScheduled(): void
    {
        PHPUnit::assertEmpty($this->tasks, 'Tasks were scheduled unexpectedly.');
    }
}
