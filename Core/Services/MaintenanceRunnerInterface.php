<?php

declare(strict_types=1);

namespace Core\Services;

interface MaintenanceRunnerInterface
{
    /**
     * Run all maintenance tasks (Queues, Schedules, Deferred tasks).
     *
     * @return string Final output/status message.
     */
    public function run(): string;
}
