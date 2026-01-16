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

class Mail
{
    /**
     * Send an email using a Mailable object
     *
     * @param Mailable $mailable The mailable notification
     *
     * @return MailStatus Send result
     */
    public static function send(Mailable $mailable): MailStatus
    {
        return resolve(Mailer::class)->send($mailable);
    }

    /**
     * Send an email deferred until after the response is sent.
     *
     * @param Mailable $mailable The mailable notification
     *
     * @return void
     */
    public static function deferred(Mailable $mailable): void
    {
        resolve(Mailer::class)->defer($mailable);
    }
}
