<?php

declare(strict_types=1);

namespace Testing\Concerns;

use Carbon\Carbon;

trait InteractsWithTime
{
    /**
     * Freeze time at a specific point.
     */
    protected function freezeTime($time = null): self
    {
        Carbon::setTestNow($time ?? Carbon::now());

        return $this;
    }

    /**
     * Travel to a specific point in time.
     */
    protected function travelTo($time, $callback = null): self
    {
        Carbon::setTestNow($time);

        if ($callback) {
            $callback();
            Carbon::setTestNow();
        }

        return $this;
    }

    protected function travel(int $value): object
    {
        return new class ($value) {
            private int $value;

            public function __construct(int $value)
            {
                $this->value = $value;
            }

            public function seconds(): void
            {
                Carbon::setTestNow(Carbon::now()->addSeconds($this->value));
            }

            public function minutes(): void
            {
                Carbon::setTestNow(Carbon::now()->addMinutes($this->value));
            }

            public function hours(): void
            {
                Carbon::setTestNow(Carbon::now()->addHours($this->value));
            }

            public function days(): void
            {
                Carbon::setTestNow(Carbon::now()->addDays($this->value));
            }

            public function weeks(): void
            {
                Carbon::setTestNow(Carbon::now()->addWeeks($this->value));
            }

            public function months(): void
            {
                Carbon::setTestNow(Carbon::now()->addMonths($this->value));
            }

            public function years(): void
            {
                Carbon::setTestNow(Carbon::now()->addYears($this->value));
            }
        };
    }

    /**
     * Travel back to the present.
     */
    protected function travelBack(): self
    {
        Carbon::setTestNow();

        return $this;
    }
}
