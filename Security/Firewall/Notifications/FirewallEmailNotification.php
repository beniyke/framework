<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Email notification for firewall alerts.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Security\Firewall\Notifications;

use Mail\Core\EmailComponent;
use Mail\EmailNotification;

class FirewallEmailNotification extends EmailNotification
{
    public function getRecipients(): array
    {
        return [
            'to' => [
                $this->payload->get('to.email') => $this->payload->get('to.name'),
            ],
        ];
    }

    public function getSubject(): string
    {
        return 'Urgent: Firewall Triggered - High Alert';
    }

    public function getTitle(): string
    {
        return 'Firewall Trigger Alert';
    }

    protected function getRawMessageContent(): string
    {
        $firewall = $this->payload->get('data.firewall');
        $message = $this->payload->get('data.message');
        $timestamp = $this->payload->get('data.timestamp');
        $ip = $this->payload->get('data.source.ip');
        $device = $this->payload->get('data.source.device');
        $browser = $this->payload->get('data.source.browser');
        $platform = $this->payload->get('data.source.platform');
        $identifier = $this->payload->get('data.identifier');

        return EmailComponent::make(false)
            ->greeting('Hello')
            ->line('This is to inform you that the ' . $firewall . ' has been triggered, indicating a potential security breach. This email serves as a high alert notification, requiring immediate intervention to assess and address the situation.')
            ->line('Here are the captured details regarding the incident:')
            ->line('<strong>Message:</strong> ' . $message)
            ->line('<strong>Timestamp:</strong> ' . $timestamp)
            ->line('<strong>Source IP:</strong> ' . $ip)
            ->line('<strong>Source Device:</strong> ' . $device)
            ->line('<strong>Source Browser:</strong> ' . $browser)
            ->line('<strong>Source Platform:</strong> ' . $platform)
            ->line('<strong>Identifier:</strong> ' . json_encode($identifier))
            ->line('Please note that this is a critical issue that demands prompt action except it isn\'t necessary.')
            ->line('Should you require any additional resources, support, or expertise, please do not hesitate to escalate this issue to the appropriate channels.')
            ->line('Your immediate attention and dedication to resolving this matter would be appreciated.')
            ->render();
    }
}
