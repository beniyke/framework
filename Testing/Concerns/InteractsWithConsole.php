<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Trait for testing CLI commands.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Concerns;

use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

trait InteractsWithConsole
{
    /**
     * The last command exit code.
     */
    protected ?int $exitCode = null;

    /**
     * The last command output.
     */
    protected string $commandOutput = '';

    /**
     * The console application.
     */
    protected ?Application $consoleApp = null;

    protected function setConsoleApplication(Application $app): void
    {
        $this->consoleApp = $app;
    }

    /**
     * Run an artisan/console command.
     *
     * @param string               $command    Command name (e.g., 'migrate:status')
     * @param array<string, mixed> $parameters Command arguments and options
     *
     * @return self
     */
    protected function artisan(string $command, array $parameters = []): self
    {
        if ($this->consoleApp === null) {
            // Try to resolve from container
            if (function_exists('resolve')) {
                $this->consoleApp = resolve(Application::class);
            } else {
                $this->consoleApp = new Application();
            }
        }

        $commandInstance = $this->consoleApp->find($command);
        $tester = new CommandTester($commandInstance);

        $this->exitCode = $tester->execute($parameters);
        $this->commandOutput = $tester->getDisplay();

        return $this;
    }

    /**
     * Run a command with inputs for interactive prompts.
     *
     * @param string               $command    Command name
     * @param array<string, mixed> $parameters Command arguments and options
     * @param array<int, string>   $inputs     Input values for prompts
     *
     * @return self
     */
    protected function artisanWithInputs(string $command, array $parameters = [], array $inputs = []): self
    {
        if ($this->consoleApp === null) {
            if (function_exists('resolve')) {
                $this->consoleApp = resolve(Application::class);
            } else {
                $this->consoleApp = new Application();
            }
        }

        $commandInstance = $this->consoleApp->find($command);
        $tester = new CommandTester($commandInstance);

        $tester->setInputs($inputs);
        $this->exitCode = $tester->execute($parameters);
        $this->commandOutput = $tester->getDisplay();

        return $this;
    }

    /**
     * Assert command exit code.
     */
    protected function assertExitCode(int $code): self
    {
        PHPUnit::assertEquals(
            $code,
            $this->exitCode,
            "Expected exit code [{$code}] but got [{$this->exitCode}]."
        );

        return $this;
    }

    /**
     * Assert command executed successfully (exit code 0).
     */
    protected function assertCommandSuccessful(): self
    {
        return $this->assertExitCode(Command::SUCCESS);
    }

    /**
     * Assert command failed (exit code != 0).
     */
    protected function assertCommandFailed(): self
    {
        PHPUnit::assertNotEquals(
            Command::SUCCESS,
            $this->exitCode,
            "Expected command to fail but it succeeded."
        );

        return $this;
    }

    /**
     * Assert command output contains a string.
     */
    protected function expectsConsoleOutput(string $text): self
    {
        PHPUnit::assertStringContainsString(
            $text,
            $this->commandOutput,
            "Expected output to contain [{$text}]."
        );

        return $this;
    }

    /**
     * Assert command output does not contain a string.
     */
    protected function doesntExpectConsoleOutput(string $text): self
    {
        PHPUnit::assertStringNotContainsString(
            $text,
            $this->commandOutput,
            "Expected output NOT to contain [{$text}]."
        );

        return $this;
    }

    /**
     * Assert command output contains a pattern.
     */
    protected function expectsOutputToMatch(string $pattern): self
    {
        PHPUnit::assertMatchesRegularExpression(
            $pattern,
            $this->commandOutput,
            "Expected output to match pattern [{$pattern}]."
        );

        return $this;
    }

    protected function getCommandOutput(): string
    {
        return $this->commandOutput;
    }

    protected function getExitCode(): ?int
    {
        return $this->exitCode;
    }
}
