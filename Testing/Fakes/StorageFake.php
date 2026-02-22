<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The files that have been "stored".
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Helpers\File\Storage\StorageAdapter;
use PHPUnit\Framework\Assert as PHPUnit;

class StorageFake extends StorageAdapter
{
    /**
     * The in-memory file system.
     */
    protected array $files = [];

    /**
     * The in-memory directories.
     */
    protected array $directories = [];

    /**
     * Determine if a file exists.
     */
    public function exists(string $path): bool
    {
        return isset($this->files[$this->normalizePath($path)]);
    }

    /**
     * Get the contents of a file.
     */
    public function get(string $path): string
    {
        return $this->files[$this->normalizePath($path)] ?? '';
    }

    /**
     * Write the contents of a file.
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        $path = $this->normalizePath($path);

        $directory = dirname($path);
        if ($directory !== '.') {
            $this->makeDirectory($directory);
        }

        $this->files[$path] = $contents;

        return true;
    }

    /**
     * Delete the file at a given path.
     */
    public function delete(string $path): bool
    {
        $path = $this->normalizePath($path);
        if (isset($this->files[$path])) {
            unset($this->files[$path]);

            return true;
        }

        return false;
    }

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool
    {
        if ($this->exists($from)) {
            return $this->put($to, $this->get($from));
        }

        return false;
    }

    /**
     * Move a file to a new location.
     */
    public function move(string $from, string $to): bool
    {
        if ($this->copy($from, $to)) {
            return $this->delete($from);
        }

        return false;
    }

    public function size(string $path): int
    {
        return strlen($this->get($path));
    }

    public function lastModified(string $path): int
    {
        return time();
    }

    public function mimeType(string $path): string
    {
        return 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return 'fake://' . $this->normalizePath($path);
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        return $this->url($path);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $directory = $this->normalizePath($directory);
        $found = [];

        foreach (array_keys($this->files) as $path) {
            if ($directory === '' || str_starts_with($path, $directory . '/')) {
                if (!$recursive) {
                    $relative = $directory === '' ? $path : substr($path, strlen($directory) + 1);
                    if (strpos($relative, '/') === false) {
                        $found[] = $path;
                    }
                } else {
                    $found[] = $path;
                }
            }
        }

        return $found;
    }

    public function makeDirectory(string $path): bool
    {
        $path = $this->normalizePath($path);
        $this->directories[$path] = true;

        $parts = explode('/', $path);
        $current = '';
        foreach ($parts as $part) {
            $current = $current === '' ? $part : $current . '/' . $part;
            $this->directories[$current] = true;
        }

        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        $path = $this->normalizePath($path);

        foreach (array_keys($this->files) as $filePath) {
            if (str_starts_with($filePath, $path . '/')) {
                unset($this->files[$filePath]);
            }
        }

        foreach (array_keys($this->directories) as $dirPath) {
            if ($dirPath === $path || str_starts_with($dirPath, $path . '/')) {
                unset($this->directories[$dirPath]);
            }
        }

        return true;
    }

    /**
     * Assert that a file exists.
     */
    public function assertExists(string $path, ?string $content = null): void
    {
        PHPUnit::assertTrue(
            $this->exists($path),
            "The expected [{$path}] file was not found."
        );

        if (!is_null($content)) {
            PHPUnit::assertEquals(
                $content,
                $this->get($path),
                "The file [{$path}] does not contain the expected content."
            );
        }
    }

    /**
     * Assert that a file does not exist.
     */
    public function assertMissing(string $path): void
    {
        PHPUnit::assertFalse(
            $this->exists($path),
            "The unexpected [{$path}] file was found."
        );
    }
}
