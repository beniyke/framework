<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake mailer for testing email dispatching.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Core\Services\ConfigServiceInterface;
use Mail\Contracts\Mailable;
use Mail\Contracts\MailDriverInterface;
use Mail\Core\EmailBuilder;
use Mail\Mailer;
use Mail\MailStatus;
use Mockery;
use PHPUnit\Framework\Assert as PHPUnit;

class MailFake extends Mailer
{
    /**
     * All of the mailables that have been sent.
     *
     * @var Mailable[]
     */
    protected array $mailables = [];

    public function __construct()
    {
        // Pass mocks to parent constructor to avoid real dependency initialization
        parent::__construct(
            Mockery::mock(MailDriverInterface::class),
            Mockery::mock(ConfigServiceInterface::class),
            Mockery::mock(EmailBuilder::class)
        );
    }

    public function send(Mailable $notification): MailStatus
    {
        $this->mailables[] = $notification;

        return new MailStatus(true, 'Fake email sent successfully.', []);
    }

    /**
     * Assert if a mailable was sent.
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

    /**
     * Get the number of sent mailables.
     */
    public function count(): int
    {
        return count($this->mailables);
    }

    public function sent(): array
    {
        return $this->mailables;
    }
}
