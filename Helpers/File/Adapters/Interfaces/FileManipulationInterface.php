<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for file manipulation operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Adapters\Interfaces;

interface FileManipulationInterface
{
    public function delete(string $path, bool $preserve = false): bool;

    public function move(string $path, string $target): bool;

    public function copy(string $directory, string $destination, ?int $flag = null): bool;

    public function mkdir(string $path, int $permission = 0755, bool $recursive = true): bool;
}
