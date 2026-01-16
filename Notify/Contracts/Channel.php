<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for notification channels.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify\Contracts;

interface Channel
{
    public function send(Notifiable $notification): mixed;
}
