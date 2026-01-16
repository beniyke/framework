<?php

declare(strict_types=1);

namespace Testing\Fakes;

use Mail\Contracts\Mailable;
use Mail\Mailer;
use Mail\MailStatus;
use PHPUnit\Framework\Assert as PHPUnit;

class MailFake extends Mailer
{
    /**
     * All of the mailables that have been sent.
     */
    protected array $mailables = [];

    public function __construct()
    {
        // We don't need the real driver or config for the fake
    }

    public function send(Mailable $notification): MailStatus
    {
        $this->mailables[] = $notification;

        return new MailStatus(true, 'Fake email sent successfully.', []);
    }

    /**
     * Assert if a mailable was sent based on a truth-test callback.
     */
    public function assertSent(string $mailable, $callback = null): void
    {
        $sentCount = count(array_filter($this->mailables, function ($m) use ($mailable, $callback) {
            if (! $m instanceof $mailable) {
                return false;
            }

            return $callback ? $callback($m) : true;
        }));

        PHPUnit::assertTrue(
            $sentCount > 0,
            "The expected [{$mailable}] mailable was not sent."
        );
    }

    /**
     * Assert if a mailable was not sent.
     */
    public function assertNotSent(string $mailable, $callback = null): void
    {
        $sentCount = count(array_filter($this->mailables, function ($m) use ($mailable, $callback) {
            if (! $m instanceof $mailable) {
                return false;
            }

            return $callback ? $callback($m) : true;
        }));

        PHPUnit::assertEquals(
            0,
            $sentCount,
            "The unexpected [{$mailable}] mailable was sent."
        );
    }

    /**
     * Assert that no mailables were sent.
     */
    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->mailables, 'Mailables were sent unexpectedly.');
    }

    public function count(): int
    {
        return count($this->mailables);
    }
}
