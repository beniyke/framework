<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface CronInterface implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cron\Interfaces;

use Cron\Task;

interface CronInterface
{
    public function command(string $signature): Task;

    public function run(): void;
}
