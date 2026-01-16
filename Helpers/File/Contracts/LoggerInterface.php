<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Contract for logging operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Contracts;

interface LoggerInterface
{
    public function log(string $level, string $message, array $context = []): void;

    public function error(string $message, array $context = []): void;

    public function warning(string $message, array $context = []): void;

    public function critical(string $message, array $context = []): void;

    public function info(string $message, array $context = []): void;

    public function debug(string $message, array $context = []): void;

    public function setLogFile(string $logFile): self;
}
