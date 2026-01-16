<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Service for handling CLI command input and parsing.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

class CliService implements CliServiceInterface
{
    protected ?string $commandName = null;

    protected array $arguments = [];

    protected array $options = [];

    public function getCommandName(): ?string
    {
        return $this->commandName;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setArguments(array $arguments): void
    {
        if (count($arguments) < 2) {
            $this->commandName = $arguments[0] ?? null;

            return;
        }

        $this->commandName = $arguments[1];
        $this->parseInput(array_slice($arguments, 2));
    }

    protected function parseInput(array $input): void
    {
        $argIndex = 0;
        foreach ($input as $item) {
            if (str_starts_with($item, '--')) {
                if (str_contains($item, '=')) {
                    [$name, $value] = explode('=', substr($item, 2), 2);
                    $this->options[$name] = $value;
                } else {
                    $this->options[substr($item, 2)] = true;
                }
            } elseif (str_starts_with($item, '-')) {
                $this->options[substr($item, 1)] = true;
            } else {
                $this->arguments[$argIndex++] = $item;
            }
        }
    }

    public function isCommand(string $name): bool
    {
        return $this->commandName === $name;
    }

    public function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        $name = ltrim($name, '-');

        if (isset($this->options[$name])) {
            return true;
        }

        if (strlen($name) > 1) {
            $shortName = substr($name, 0, 1);
            if (isset($this->options[$shortName])) {
                return true;
            }
        }

        return false;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        $name = ltrim($name, '-');

        if (isset($this->options[$name])) {
            return $this->options[$name];
        }

        if (strlen($name) > 1) {
            $shortName = substr($name, 0, 1);
            if (isset($this->options[$shortName])) {
                return $this->options[$shortName];
            }
        }

        return $default;
    }
}
