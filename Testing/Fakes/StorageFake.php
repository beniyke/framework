<?php

declare(strict_types=1);

namespace Testing\Fakes;

use Helpers\File\FileSystem;
use PHPUnit\Framework\Assert as PHPUnit;

class StorageFake extends FileSystem
{
    /**
     * The files that have been "stored".
     */
    protected array $files = [];

    /**
     * Determine if a file exists.
     */
    public static function exists(string $filename): bool
    {
        // This is static, so we might need a non-static way to track files if we want to mock the core FileSystem.
        // However, for a 'StorageFake', we usually mock a Disk instance.
        return false;
    }

    /**
     * Put content into a file.
     */
    public static function put(string $path, string $content, bool $lock = false): bool
    {
        // Tracking static calls is hard without a singleton registry.
        return true;
    }

    /**
     * Assert that a file exists.
     */
    public function assertExists(string $path): void
    {
        PHPUnit::assertTrue(
            isset($this->files[$path]),
            "The expected [{$path}] file was not found."
        );
    }

    /**
     * Assert that a file does not exist.
     */
    public function assertMissing(string $path): void
    {
        PHPUnit::assertFalse(
            isset($this->files[$path]),
            "The unexpected [{$path}] file was found."
        );
    }
}
