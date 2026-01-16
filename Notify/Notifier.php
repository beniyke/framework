<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class acts as a factory for creating NotificationBuilder instances for specific channels.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify;

class Notifier
{
    private readonly NotificationManager $manager;

    public function __construct(NotificationManager $manager)
    {
        $this->manager = $manager;
    }

    public function channel(string $channel): NotificationBuilder
    {
        return new NotificationBuilder($this->manager, $channel);
    }
}
