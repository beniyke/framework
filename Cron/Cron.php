<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class Cron implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cron;

use Cron\Interfaces\CronInterface;

class Cron
{
    public static function command(string $signature): Task
    {
        return resolve(CronInterface::class)->command($signature);
    }
}
