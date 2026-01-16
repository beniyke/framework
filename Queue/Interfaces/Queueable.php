<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Contract for any job class that can be placed into the queue.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Interfaces;

use Queue\Scheduler;

interface Queueable
{
    public function period(Scheduler $scheduler): Scheduler;

    public function run(): object;
}
