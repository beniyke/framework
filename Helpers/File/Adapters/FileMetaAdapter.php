<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Adapter for file metadata operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Adapters;

use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Helpers\File\FileSystem;

class FileMetaAdapter implements FileMetaInterface
{
    public function hash(string $path): string
    {
        return FileSystem::hash($path);
    }

    public function exists(string $filename): bool
    {
        return FileSystem::exists($filename);
    }

    public function isDir(string $path): bool
    {
        return FileSystem::isDir($path);
    }

    public function isFile(string $path): bool
    {
        return FileSystem::isFile($path);
    }

    public function isReadable(string $path): bool
    {
        return FileSystem::isReadable($path);
    }

    public function size(string $path): int
    {
        return FileSystem::size($path);
    }

    public function chmod(string $path, int $permission): bool
    {
        return FileSystem::chmod($path, $permission);
    }

    public function permissions(string $path): string
    {
        return FileSystem::permissions($path);
    }
}
