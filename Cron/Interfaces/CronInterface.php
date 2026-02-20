<?php

declare(strict_types=1);

namespace Cron\Interfaces;

use Cron\Task;

interface CronInterface
{
    public function command(string $signature): Task;

    public function run(): void;
}
