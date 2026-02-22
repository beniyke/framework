<?php

declare(strict_types=1);

namespace Cron\Interfaces;

use Cron\Schedule;

interface Schedulable
{
    /**
 * Anchor Framework
 *
 * Define the schedule for the task(s).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
    public function schedule(Schedule $schedule): void;
}
