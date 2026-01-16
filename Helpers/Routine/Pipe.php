<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Pipe executes a sequence of tasks where each task receives the result of the previous one.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Routine;

use Closure;

class Pipe
{
    /** @var callable[] */
    protected array $stages = [];

    protected mixed $initial = null;

    protected ?Closure $finalCallback = null;

    public static function start(mixed $initial = null): self
    {
        $pipe = new self();
        $pipe->initial = $initial;

        return $pipe;
    }

    public function through(callable $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    public function then(callable $finalCallback): self
    {
        $this->finalCallback = $finalCallback;

        return $this;
    }

    public function run(): mixed
    {
        $result = $this->initial;

        foreach ($this->stages as $stage) {
            $result = $stage($result);
        }

        return $this->finalCallback ? ($this->finalCallback)($result) : $result;
    }
}
