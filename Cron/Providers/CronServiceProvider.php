<?php

declare(strict_types=1);

namespace Cron\Providers;

use Core\Services\ServiceProvider;
use Cron\Interfaces\CronInterface;
use Cron\Schedule;

class CronServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(CronInterface::class, Schedule::class);
    }
}
