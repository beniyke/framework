<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Task for sending queued emails.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Tasks;

use Mail\Contracts\Mailable;
use Mail\Mailer;
use Queue\BaseTask;
use Queue\Scheduler;

class SendMailTask extends BaseTask
{
    public function occurrence(): string
    {
        return self::once();
    }

    public function period(Scheduler $schedule): Scheduler
    {
        return $schedule;
    }

    protected function execute(): bool
    {
        $mailable = $this->payload->get('mailable');

        if ($mailable instanceof Mailable) {
            return resolve(Mailer::class)->send($mailable)->isSuccess();
        }

        error_log('SendMailTask: Passing raw mailable objects is restricted. Use specific Task classes.');

        return false;
    }

    protected function successMessage(): string
    {
        return 'Email sent successfully via queue.';
    }

    protected function failedMessage(): string
    {
        return 'Failed to send email via queue.';
    }
}
