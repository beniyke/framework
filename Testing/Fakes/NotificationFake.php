<?php

declare(strict_types=1);

namespace Testing\Fakes;

use Notify\Contracts\Notifiable;
use Notify\NotificationManager;
use PHPUnit\Framework\Assert as PHPUnit;

class NotificationFake extends NotificationManager
{
    /**
     * All of the notifications that have been sent.
     */
    protected array $notifications = [];

    public function send(string $channelName, Notifiable $notification, ?callable $before = null, ?callable $after = null): mixed
    {
        $this->notifications[] = [
            'channel' => $channelName,
            'notification' => $notification,
        ];

        if ($before) {
            $before();
        }

        $response = ['success' => true, 'fake' => true];

        if ($after) {
            $response = $after($response);
        }

        return $response;
    }

    /**
     * Assert if a notification was sent.
     */
    public function assertSentTo(string $notifiable, string $notification, $callback = null): void
    {
        // For Anchor, 'notifiable' might be an email or a user ID
        // In this fake, we just track the notification class for now

        $sentCount = count(array_filter($this->notifications, function ($n) use ($notification, $callback) {
            if (! $n['notification'] instanceof $notification) {
                return false;
            }

            return $callback ? $callback($n['notification'], $n['channel']) : true;
        }));

        PHPUnit::assertTrue(
            $sentCount > 0,
            "The expected [{$notification}] notification was not sent."
        );
    }

    /**
     * Assert if a notification was not sent.
     */
    public function assertNotSentTo(string $notifiable, string $notification, $callback = null): void
    {
        $sentCount = count(array_filter($this->notifications, function ($n) use ($notification, $callback) {
            if (! $n['notification'] instanceof $notification) {
                return false;
            }

            return $callback ? $callback($n['notification'], $n['channel']) : true;
        }));

        PHPUnit::assertEquals(
            0,
            $sentCount,
            "The unexpected [{$notification}] notification was sent."
        );
    }
}
