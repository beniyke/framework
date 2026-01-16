<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Adapter for file read/write operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Adapters;

use Helpers\File\Adapters\Interfaces\FileReadWriteInterface;
use Helpers\File\FileSystem;
use RecursiveIteratorIterator;

class FileReadWriteAdapter implements FileReadWriteInterface
{
    public function get(string $path, bool $lock = false): string
    {
        return FileSystem::get($path, $lock);
    }

    public function put(string $path, string $content, bool $lock = false): bool
    {
        return FileSystem::put($path, $content, $lock);
    }

    public function replace(string $path, string $content): bool
    {
        return FileSystem::replace($path, $content);
    }

    public function prepend(string $path, string $data): bool
    {
        return FileSystem::prepend($path, $data);
    }

    public function append(string $path, string $data): bool
    {
        return FileSystem::append($path, $data);
    }

    public function getDirectoryContents(string $path, ?int $flag = null): ?RecursiveIteratorIterator
    {
        return FileSystem::contents($path, $flag);
    }
}
