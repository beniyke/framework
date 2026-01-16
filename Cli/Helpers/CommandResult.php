<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Immutable result object for command execution.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Helpers;

use RuntimeException;

class CommandResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
        public readonly string $error,
        public readonly int $exitCode,
        public readonly string $commandLine,
        public readonly float $executionTime
    ) {
    }

    /**
     * Check if command executed successfully
     */
    public function successful(): bool
    {
        return $this->success;
    }

    public function failed(): bool
    {
        return ! $this->success;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getCommandLine(): string
    {
        return $this->commandLine;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Throw exception if command failed
     */
    public function throw(): self
    {
        if ($this->failed()) {
            throw new RuntimeException(
                "Command failed with exit code {$this->exitCode}: {$this->error}\nCommand: {$this->commandLine}"
            );
        }

        return $this;
    }

    /**
     * Execute callback if command was successful
     */
    public function onSuccess(callable $callback): self
    {
        if ($this->successful()) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Execute callback if command failed
     */
    public function onFailure(callable $callback): self
    {
        if ($this->failed()) {
            $callback($this);
        }

        return $this;
    }
}
