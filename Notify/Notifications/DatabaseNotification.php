<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Base class for handling InApp notifications.
 * This class provides a structure for building InApp notifications
 * and requires extending classes to implement key notification details.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify\Notifications;

use Helpers\Data;
use Notify\Contracts\DatabaseNotifiable;

abstract class DatabaseNotification implements DatabaseNotifiable
{
    protected Data $payload;

    public function __construct(Data $payload)
    {
        $this->payload = $payload;
    }

    public function toDatabase(): Data
    {
        $notification = [
            'user_id' => $this->getUser(),
            'message' => $this->getMessage(),
            'url' => $this->getUrl(),
            'label' => $this->getLabel(),
        ];

        return Data::make($notification);
    }

    /**
     * Retrieves the user ID associated with the notification.
     */
    abstract public function getUser(): int;

    /**
     * Retrieves the message content of the notification.
     */
    abstract public function getMessage(): string;

    /**
     * Retrieves the label of the notification.
     */
    abstract public function getLabel(): string;

    /**
     * Retrieves the URL associated with the notification.
     */
    abstract public function getUrl(): ?string;
}
