<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The NotificationManager is responsible for registering channels and dispatching
 * notifications to the appropriate channel.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify;

use InvalidArgumentException;
use Notify\Contracts\Channel;
use Notify\Contracts\Notifiable;

class NotificationManager
{
    protected array $channels = [];

    public function registerChannel(string $name, Channel $channel): void
    {
        $this->channels[$name] = $channel;
    }

    public function send(string $channelName, Notifiable $notification, ?callable $before = null, ?callable $after = null): mixed
    {
        if (! isset($this->channels[$channelName])) {
            throw new InvalidArgumentException("Channel '{$channelName}' is not registered.");
        }

        if ($before) {
            $before();
        }

        $response = $this->channels[$channelName]->send($notification);

        if ($after) {
            $response = $after($response);
        }

        return $response;
    }

    public function defer(string $channelName, Notifiable $notification, ?callable $before = null, ?callable $after = null): void
    {
        defer(function () use ($channelName, $notification, $before, $after) {
            $this->send($channelName, $notification, $before, $after);
        });
    }
}
