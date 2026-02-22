<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class NullAdapter implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Storage\Adapters;

use Helpers\File\Storage\StorageAdapter;

class NullAdapter extends StorageAdapter
{
    public function exists(string $path): bool
    {
        return false;
    }

    public function get(string $path): string
    {
        return '';
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        return true;
    }

    public function delete(string $path): bool
    {
        return true;
    }

    public function copy(string $from, string $to): bool
    {
        return true;
    }

    public function move(string $from, string $to): bool
    {
        return true;
    }

    public function size(string $path): int
    {
        return 0;
    }

    public function lastModified(string $path): int
    {
        return 0;
    }

    public function mimeType(string $path): string
    {
        return 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return '';
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        return '';
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        return [];
    }

    public function makeDirectory(string $path): bool
    {
        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        return true;
    }
}
