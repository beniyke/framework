<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Executes a list of tasks in sequence, optionally running "before" and "after" callbacks.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Routine;

use Closure;
use Generator;

class Sync
{
    /** @var callable[] */
    protected array $tasks = [];

    protected ?Closure $beforeCallback = null;

    protected ?Closure $afterCallback = null;

    public static function new(): self
    {
        return new self();
    }

    public function before(callable $callback): self
    {
        $this->beforeCallback = $callback;

        return $this;
    }

    public function after(callable $callback): self
    {
        $this->afterCallback = $callback;

        return $this;
    }

    public function task(callable $task): self
    {
        $this->tasks[] = $task;

        return $this;
    }

    public function execute(): array
    {
        $results = [];

        foreach ($this->generateTaskResults() as $result) {
            $results[] = $result;
        }

        return $results;
    }

    private function handleTask(callable $task): mixed
    {
        $input = $this->beforeCallback ? ($this->beforeCallback)() : null;
        $output = $task($input);

        return $this->afterCallback ? ($this->afterCallback)($output) : $output;
    }

    private function generateTaskResults(): Generator
    {
        foreach ($this->tasks as $task) {
            yield $this->handleTask($task);
        }
    }
}
