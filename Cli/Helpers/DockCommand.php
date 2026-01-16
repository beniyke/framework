<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fluent API for building and executing dock commands.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Helpers;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DockCommand
{
    protected string $command = '';

    protected array $arguments = [];

    protected array $options = [];

    protected array $flags = [];

    protected int $timeout = 3600;

    protected ?int $retries = null;

    protected int $retryDelay = 1000; // milliseconds

    protected bool $dryRun = false;

    protected array $env = [];

    /**
     * Create a new dock command instance
     */
    public static function make(string $command = ''): self
    {
        $instance = new self();
        if ($command) {
            $instance->command = $command;
        }

        return $instance;
    }

    public function command(string $command): self
    {
        $this->command = $command;

        return $this;
    }

    public function argument(string $value): self
    {
        $this->arguments[] = $value;

        return $this;
    }

    public function arguments(array $values): self
    {
        foreach ($values as $value) {
            $this->argument($value);
        }

        return $this;
    }

    public function option(string $name, ?string $value = null): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function options(array $options): self
    {
        foreach ($options as $name => $value) {
            $this->option($name, $value);
        }

        return $this;
    }

    public function flag(string $name): self
    {
        $this->flags[] = $name;

        return $this;
    }

    public function flags(array $flags): self
    {
        foreach ($flags as $flag) {
            $this->flag($flag);
        }

        return $this;
    }

    /**
     * Set command timeout in seconds
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function retry(int $times, int $delayMs = 1000): self
    {
        $this->retries = $times;
        $this->retryDelay = $delayMs;

        return $this;
    }

    public function env(array $env): self
    {
        $this->env = array_merge($this->env, $env);

        return $this;
    }

    /**
     * Enable dry-run mode (doesn't execute, just returns command string)
     */
    public function dryRun(bool $enabled = true): self
    {
        $this->dryRun = $enabled;

        return $this;
    }

    /**
     * Build the full command string
     */
    public function buildCommand(): string
    {
        if (empty($this->command)) {
            throw new RuntimeException('Command name is required');
        }

        $parts = ['php', 'dock', $this->command];

        // Add arguments
        foreach ($this->arguments as $arg) {
            $parts[] = $this->escapeArgument($arg);
        }

        // Add options
        foreach ($this->options as $name => $value) {
            if ($value === null) {
                $parts[] = "--{$name}";
            } else {
                $parts[] = "--{$name}=" . $this->escapeArgument($value);
            }
        }

        // Add flags
        foreach ($this->flags as $flag) {
            $parts[] = "--{$flag}";
        }

        return implode(' ', $parts);
    }

    /**
     * Execute the command and return result
     */
    public function run(): CommandResult
    {
        if ($this->dryRun) {
            return new CommandResult(
                success: true,
                output: $this->buildCommand(),
                error: '',
                exitCode: 0,
                commandLine: $this->buildCommand(),
                executionTime: 0.0
            );
        }

        $attempt = 0;
        $maxAttempts = $this->retries !== null ? $this->retries + 1 : 1;

        while ($attempt < $maxAttempts) {
            $result = $this->executeCommand();

            if ($result->successful() || $attempt === $maxAttempts - 1) {
                return $result;
            }

            $attempt++;
            if ($this->retryDelay > 0) {
                usleep($this->retryDelay * 1000);
            }
        }

        return $result;
    }

    /**
     * Execute command quietly (suppresses exceptions, returns result)
     */
    public function runQuietly(): CommandResult
    {
        try {
            return $this->run();
        } catch (Throwable $e) {
            return new CommandResult(
                success: false,
                output: '',
                error: $e->getMessage(),
                exitCode: 1,
                commandLine: $this->buildCommand(),
                executionTime: 0.0
            );
        }
    }

    /**
     * Execute command asynchronously (non-blocking)
     */
    public function runAsync(): Process
    {
        $commandLine = $this->buildCommand();
        $process = Process::fromShellCommandline($commandLine);

        if (! empty($this->env)) {
            $process->setEnv($this->env);
        }

        $process->setTimeout($this->timeout);
        $process->start();

        return $process;
    }

    /**
     * Execute the command and return result
     */
    protected function executeCommand(): CommandResult
    {
        $commandLine = $this->buildCommand();
        $process = Process::fromShellCommandline($commandLine);

        if (! empty($this->env)) {
            $process->setEnv($this->env);
        }

        $process->setTimeout($this->timeout);

        $startTime = microtime(true);

        try {
            $process->run();
            $executionTime = microtime(true) - $startTime;

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            $exitCode = $process->getExitCode() ?? 0;
            $success = $process->isSuccessful();

            return new CommandResult(
                success: $success,
                output: trim($output),
                error: trim($errorOutput ?: ($success ? '' : $output)),
                exitCode: $exitCode,
                commandLine: $commandLine,
                executionTime: $executionTime
            );
        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            throw new RuntimeException(
                "Command execution failed: {$e->getMessage()}\nCommand: {$commandLine}",
                0,
                $e
            );
        }
    }

    /**
     * Escape argument for shell
     */
    protected function escapeArgument(string $arg): string
    {
        // If argument contains spaces or special characters, quote it
        if (preg_match('/[\s<>|&;()]/', $arg)) {
            return '"' . str_replace('"', '\\"', $arg) . '"';
        }

        return $arg;
    }
}
