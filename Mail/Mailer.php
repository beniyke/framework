<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The Mailer class hanles the actual sending of emails using the configured driver.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail;

use Core\Services\ConfigServiceInterface;
use InvalidArgumentException;
use Mail\Contracts\Mailable;
use Mail\Contracts\MailDriverInterface;
use Mail\Core\EmailBuilder;

class Mailer
{
    private MailDriverInterface $driver;

    private ConfigServiceInterface $config;

    private EmailBuilder $builder;

    public function __construct(MailDriverInterface $driver, ConfigServiceInterface $config, EmailBuilder $builder)
    {
        $this->driver = $driver;
        $this->config = $config;
        $this->builder = $builder;
    }

    public function send(Mailable $notification): MailStatus
    {
        if (! method_exists($notification, 'toMail')) {
            throw new InvalidArgumentException('Notification object must have a toMail() method.');
        }

        $payload = $notification->toMail($this->builder);

        $from = $payload->get('from') ?? $this->config->get('mail.sender');
        $recipients = $payload->get('recipients') ?? [];
        $subject = $payload->get('subject') ?? '';
        $message = $payload->get('message') ?? '';
        $attachment = $payload->get('attachment') ?? [];

        return $this->driver->send($from, $subject, $recipients, $message, $attachment);
    }

    public function defer(Mailable $notification): void
    {
        defer(function () use ($notification) {
            $this->send($notification);
        });
    }
}
