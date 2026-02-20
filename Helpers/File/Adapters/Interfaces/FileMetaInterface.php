<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for file metadata operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Adapters\Interfaces;

interface FileMetaInterface
{
    public function hash(string $path): string;

    public function exists(string $filename): bool;

    public function isDir(string $path): bool;

    public function isFile(string $path): bool;

    public function isReadable(string $path): bool;

    public function size(string $path): int;

    public function directorySize(string $path): int;

    public function chmod(string $path, int $permission): bool;

    public function lastModified(string $path): int;

    public function mimeType(string $path): string;

    public function extension(string $path): string;

    public function permissions(string $path): string;
}
