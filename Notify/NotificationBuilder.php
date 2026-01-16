<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * NotificationBuilder class.
 * Facilitates the fluent construction and dispatching of notifications.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify;

use BadMethodCallException;
use Core\Ioc\Container;

class NotificationBuilder
{
    protected NotificationManager $manager;

    protected string $channelName;

    protected string $notificationClass;

    protected object $dataPayload;

    protected $beforeCallback = null;

    protected $afterCallback = null;

    public function __construct(NotificationManager $manager, string $channelName)
    {
        $this->manager = $manager;
        $this->channelName = $channelName;
    }

    public function with(string $notificationClass, object $dataPayload): self
    {
        $this->notificationClass = $notificationClass;
        $this->dataPayload = $dataPayload;

        return $this;
    }

    public function before(callable $callback): self
    {
        $this->beforeCallback = $callback;

        return $this;
    }

    public function after(callable $callback): self
    {
        $this->afterCallback = $callback;

        return $this;
    }

    public function send(): mixed
    {
        if (empty($this->notificationClass)) {
            throw new BadMethodCallException('A notification class must be set using the with() method before calling send().');
        }

        $notification = $this->resolveNotification();

        return $this->manager->send($this->channelName, $notification, $this->beforeCallback, $this->afterCallback);
    }

    private function resolveNotification(): object
    {
        return Container::getResolvedInstance()->make($this->notificationClass, ['payload' => $this->dataPayload]);
    }
}
