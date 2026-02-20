<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Mail Static Facade.
 * Provides convenient static methods for sending emails.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail;

use Mail\Contracts\Mailable;
use Queue\Queue;

class Mail
{
    /**
     * Send an email using a Mailable object
     */
    public static function send(Mailable $mailable): MailStatus
    {
        return resolve(Mailer::class)->send($mailable);
    }

    /**
     * Send an email deferred until after the response is sent.
     */
    public static function deferred(Mailable $mailable): void
    {
        resolve(Mailer::class)->defer($mailable);
    }

    /**
     * Queue an email task to be sent asynchronously.
     * Use this to dispatch a task that extends BaseTask.
     */
    public static function queue(string $taskClass, mixed $data = [], string $queue = 'default'): void
    {
        Queue::deferred($taskClass, $data, $queue);
    }
}
