<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Base class for handling message notifications.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify\Notifications;

use Helpers\Data;
use Notify\Contracts\MessageNotifiable;

abstract class MessageNotification implements MessageNotifiable
{
    protected Data $payload;

    public function __construct(Data $payload)
    {
        $this->payload = $payload;
    }

    public function toMessage(): Data
    {
        $notification = [
            'recipient' => $this->getRecipient(),
            'message' => $this->getMessage()
        ];

        return Data::make($notification);
    }

    /**
     * Retrieves the recipient of the notification.
     */
    abstract public function getRecipient(): string;

    /**
     * Retrieves the message content of the notification.
     */
    abstract public function getMessage(): string;
}
