<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This interface defines the contract for mail drivers.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Contracts;

interface MailDriverInterface
{
    public function send(array $from, string $subject, array $to, string $message, array $attachment = []): \Mail\MailStatus;
}
