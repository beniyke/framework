<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for the Queue Dispatcher.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Queue\Interfaces;

interface QueueDispatcherInterface
{
    public function pending(): self;

    public function failed(): self;

    public function run(): string;
}
