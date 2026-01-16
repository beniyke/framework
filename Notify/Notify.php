<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Provides convenient static methods for sending notifications.
 * Supports dynamic channel methods via __callStatic for infinite extensibility.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify;

use BadMethodCallException;
use Notify\Contracts\Notifiable;

class Notify
{
    public static function channel(string $channelName): NotificationBuilder
    {
        return resolve(Notifier::class)->channel($channelName);
    }

    public static function email(string $notificationClass, object $payload): mixed
    {
        return static::channel('email')
            ->with($notificationClass, $payload)
            ->send();
    }

    public static function inapp(string $notificationClass, object $payload): mixed
    {
        return static::channel('in-app')
            ->with($notificationClass, $payload)
            ->send();
    }

    public static function send(string $channelName, Notifiable $notification): mixed
    {
        return resolve(NotificationManager::class)->send($channelName, $notification);
    }

    /**
     * Send a notification deferred until after the response is sent.
     */
    public static function deferred(string $channelName, Notifiable $notification): void
    {
        resolve(NotificationManager::class)->defer($channelName, $notification);
    }

    /**
     * Magic method to handle dynamic channel calls
     *
     * Allows calling any channel as a static method without defining it explicitly.
     * Examples: Notify::whatsapp(), Notify::slack(), Notify::telegram(), etc.
     */
    public static function __callStatic(string $channelName, array $arguments): mixed
    {
        if (count($arguments) < 2) {
            throw new BadMethodCallException(
                "Notify::{$channelName}() requires 2 arguments: notificationClass and payload"
            );
        }

        [$notificationClass, $payload] = $arguments;

        return static::channel($channelName)
            ->with($notificationClass, $payload)
            ->send();
    }
}
