<?php

declare(strict_types=1);

namespace Cron\Interfaces;

use Cron\Schedule;

interface Schedulable
{
    /**
     * Define the schedule for the task(s).
     *
     * @param Schedule $schedule
     *
     * @return void
     */
    public function schedule(Schedule $schedule): void;
}
