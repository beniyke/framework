<?php

declare(strict_types=1);

namespace Core\Services;

interface BackgroundDispatcherInterface
{
    /**
 * Anchor Framework
 *
 * Run all background tasks (Queues, Schedules, Deferred tasks).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */
    public function run(): string;
}
