<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for file read/write operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Adapters\Interfaces;

use RecursiveIteratorIterator;

interface FileReadWriteInterface
{
    public function get(string $path, bool $lock = false): string;

    public function put(string $path, string $content, bool $lock = false): bool;

    public function replace(string $path, string $content): bool;

    public function prepend(string $path, string $data): bool;

    public function append(string $path, string $data): bool;

    public function getDirectoryContents(string $path, ?int $flag = null): ?RecursiveIteratorIterator;
}
