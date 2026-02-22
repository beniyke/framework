<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake logger for testing logging operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Helpers\File\Contracts\LoggerInterface;
use PHPUnit\Framework\Assert as PHPUnit;

class LogFake implements LoggerInterface
{
    protected array $logs = [];

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function setLogFile(string $logFile): self
    {
        return $this;
    }

    public function assertLogged(string $level, $callback = null): void
    {
        $loggedCount = count(array_filter($this->logs, function ($l) use ($level, $callback) {
            if ($l['level'] !== $level) {
                return false;
            }

            return $callback ? $callback($l['message'], $l['context']) : true;
        }));

        PHPUnit::assertTrue(
            $loggedCount > 0,
            "The expected log entry with level [{$level}] was not found."
        );
    }

    public function assertNotLogged(string $level, $callback = null): void
    {
        $loggedCount = count(array_filter($this->logs, function ($l) use ($level, $callback) {
            if ($l['level'] !== $level) {
                return false;
            }

            return $callback ? $callback($l['message'], $l['context']) : true;
        }));

        PHPUnit::assertEquals(
            0,
            $loggedCount,
            "The unexpected log entry with level [{$level}] was found."
        );
    }

    public function assertNothingLogged(): void
    {
        PHPUnit::assertEmpty($this->logs, 'Log entries were created unexpectedly.');
    }
}
