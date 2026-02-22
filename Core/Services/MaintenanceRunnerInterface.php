<?php

declare(strict_types=1);

namespace Core\Services;

interface MaintenanceRunnerInterface
{
    /**
 * Anchor Framework
 *
 * Run all maintenance tasks (Queues, Schedules, Deferred tasks).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
    public function run(): string;
}
