<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for the CLI Service.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Services;

interface CliServiceInterface
{
    public function setArguments(array $arguments): void;

    public function isCommand(string $name): bool;

    public function getArgument(int $index, mixed $default = null): mixed;

    public function hasOption(string $name): bool;

    public function getOption(string $name, mixed $default = null): mixed;
}
