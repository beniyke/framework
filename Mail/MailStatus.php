<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * MailStatus Object.
 * Represents the result of a mail sending operation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail;

class MailStatus
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message
    ) {
    }

    public static function success(string $message = 'Mail successfully sent'): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isSent(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return ! $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
