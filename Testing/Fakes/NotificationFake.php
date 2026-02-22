<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * All of the notifications that have been sent.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Notify\Contracts\ChannelAware;
use Notify\Contracts\Notifiable;
use Notify\NotificationManager;
use PHPUnit\Framework\Assert as PHPUnit;

class NotificationFake extends NotificationManager
{
    protected array $notifications = [];

    public function send(string $channelName, Notifiable $notification, ?callable $before = null, ?callable $after = null): mixed
    {
        // Track the notification and its channel
        $this->notifications[] = [
            'channel' => $channelName,
            'notification' => $notification,
            'notifiable' => $notification instanceof ChannelAware ? $notification->getNotifiable() : null,
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
     *
     * @param Notifiable|string $notifiable
     * @param string            $notification
     * @param callable|null     $callback
     */
    public function assertSentTo($notifiable, string $notification, $callback = null): void
    {
        $sentCount = count(array_filter($this->notifications, function ($n) use ($notifiable, $notification, $callback) {
            if (! ($n['notification'] instanceof $notification)) {
                return false;
            }

            // If notifiable is specificed, check it
            if ($notifiable && $n['notifiable'] !== $notifiable) {
                // If the notification itself tracks who it's for differently,
                // we might need more complex logic here.
                return false;
            }

            return $callback ? $callback($n['notification'], $n['channel']) : true;
        }));

        PHPUnit::assertTrue(
            $sentCount > 0,
            "The expected [{$notification}] notification was not sent to [{$notifiable}]."
        );
    }

    /**
     * Assert if a notification was not sent.
     */
    public function assertNotSentTo($notifiable, string $notification, $callback = null): void
    {
        $sentCount = count(array_filter($this->notifications, function ($n) use ($notifiable, $notification, $callback) {
            if (! ($n['notification'] instanceof $notification)) {
                return false;
            }

            if ($notifiable && $n['notifiable'] !== $notifiable) {
                return false;
            }

            return $callback ? $callback($n['notification'], $n['channel']) : true;
        }));

        PHPUnit::assertEquals(
            0,
            $sentCount,
            "The unexpected [{$notification}] notification was sent to [{$notifiable}]."
        );
    }

    /**
     * Assert that no notifications were sent.
     */
    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->notifications, 'Notifications were sent unexpectedly.');
    }
}
