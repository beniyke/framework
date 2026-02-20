<?php

declare(strict_types=1);

namespace Cron;

use Cron\Interfaces\CronInterface;

class Cron
{
    public static function command(string $signature): Task
    {
        return resolve(CronInterface::class)->command($signature);
    }
}
