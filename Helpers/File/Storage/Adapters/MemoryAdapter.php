<?php

declare(strict_types=1);

namespace Helpers\File\Storage\Adapters;

use Helpers\File\Storage\StorageAdapter;

class MemoryAdapter extends StorageAdapter
{
    /**
     * The in-memory storage.
     *
     * @var array<string, string>
     */
    protected array $storage = [];

    /**
     * The directory metadata.
     *
     * @var array<string, bool>
     */
    protected array $directories = [];

    public function exists(string $path): bool
    {
        return isset($this->storage[$this->normalizePath($path)]);
    }

    public function get(string $path): string
    {
        return $this->storage[$this->normalizePath($path)] ?? '';
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        $path = $this->normalizePath($path);

        // Ensure parent directories exist (simulated)
        $directory = dirname($path);
        if ($directory !== '.') {
            $this->makeDirectory($directory);
        }

        $this->storage[$path] = $contents;

        return true;
    }

    public function delete(string $path): bool
    {
        $path = $this->normalizePath($path);
        if (isset($this->storage[$path])) {
            unset($this->storage[$path]);

            return true;
        }

        return false;
    }

    public function copy(string $from, string $to): bool
    {
        if ($this->exists($from)) {
            return $this->put($to, $this->get($from));
        }

        return false;
    }

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
        // Memory adapter doesn't track modification time by default
        return time();
    }

    public function mimeType(string $path): string
    {
        return 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return 'memory://' . $this->normalizePath($path);
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        return $this->url($path);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $directory = $this->normalizePath($directory);
        $files = [];

        foreach (array_keys($this->storage) as $path) {
            if ($directory === '' || str_starts_with($path, $directory . '/')) {
                if (!$recursive) {
                    $relative = $directory === '' ? $path : substr($path, strlen($directory) + 1);
                    if (strpos($relative, '/') === false) {
                        $files[] = $path;
                    }
                } else {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    public function makeDirectory(string $path): bool
    {
        $path = $this->normalizePath($path);
        $this->directories[$path] = true;

        // Recursively add parent directories
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

        // Remove all files starting with this path
        foreach (array_keys($this->storage) as $filePath) {
            if (str_starts_with($filePath, $path . '/')) {
                unset($this->storage[$filePath]);
            }
        }

        // Remove directories
        foreach (array_keys($this->directories) as $dirPath) {
            if ($dirPath === $path || str_starts_with($dirPath, $path . '/')) {
                unset($this->directories[$dirPath]);
            }
        }

        return true;
    }
}
