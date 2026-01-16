<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Adapter for file manipulation operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Adapters;

use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\FileSystem;

class FileManipulationAdapter implements FileManipulationInterface
{
    public function delete(string $path, bool $preserve = false): bool
    {
        return FileSystem::delete($path, $preserve);
    }

    public function move(string $path, string $target): bool
    {
        return FileSystem::move($path, $target);
    }

    public function copy(string $directory, string $destination, ?int $flag = null): bool
    {
        return FileSystem::copy($directory, $destination, $flag);
    }

    public function mkdir(string $path, int $permission = 0755, bool $recursive = true): bool
    {
        return FileSystem::mkdir($path, $permission, $recursive);
    }
}
