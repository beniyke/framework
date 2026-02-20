<?php

declare(strict_types=1);

namespace Helpers\File\Storage;

interface StorageInterface
{
    /**
     * Determine if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Get the contents of a file.
     */
    public function get(string $path): string;

    /**
     * Write the contents of a file.
     */
    public function put(string $path, string $contents, array $options = []): bool;

    /**
     * Delete the file at a given path.
     */
    public function delete(string $path): bool;

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file to a new location.
     */
    public function move(string $from, string $to): bool;

    public function size(string $path): int;

    public function lastModified(string $path): int;

    public function mimeType(string $path): string;

    public function url(string $path): string;

    public function temporaryUrl(string $path, int $expiration, array $options = []): string;

    public function files(string $directory = '', bool $recursive = false): array;

    public function makeDirectory(string $path): bool;

    public function deleteDirectory(string $path): bool;

    public function readStream(string $path, array $options = []): mixed;

    public function writeStream(string $path, $resource, array $options = []): bool;

    public function generateSignature(string $path, int $expiration): string;

    public function hasValidSignature(string $path, int $expiration, string $signature): bool;
}
