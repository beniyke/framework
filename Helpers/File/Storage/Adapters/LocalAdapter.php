<?php

declare(strict_types=1);

namespace Helpers\File\Storage\Adapters;

use Helpers\File\FileSystem;
use Helpers\File\Storage\StorageAdapter;

class LocalAdapter extends StorageAdapter
{
    protected string $root;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->root = rtrim($config['root'], DIRECTORY_SEPARATOR);

        if (!FileSystem::isDir($this->root)) {
            FileSystem::mkdir($this->root, 0755, true);
        }
    }

    public function exists(string $path): bool
    {
        return FileSystem::exists($this->fullPath($path));
    }

    public function get(string $path): string
    {
        return FileSystem::get($this->fullPath($path));
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        $fullPath = $this->fullPath($path);
        $directory = dirname($fullPath);

        if (!FileSystem::isDir($directory)) {
            FileSystem::mkdir($directory, 0755, true);
        }

        return FileSystem::put($fullPath, $contents, $options['lock'] ?? false);
    }

    public function delete(string $path): bool
    {
        return FileSystem::delete($this->fullPath($path));
    }

    public function copy(string $from, string $to): bool
    {
        return FileSystem::copy($this->fullPath($from), $this->fullPath($to));
    }

    public function move(string $from, string $to): bool
    {
        return FileSystem::move($this->fullPath($from), $this->fullPath($to));
    }

    public function size(string $path): int
    {
        return FileSystem::size($this->fullPath($path));
    }

    public function lastModified(string $path): int
    {
        return FileSystem::lastModified($this->fullPath($path));
    }

    public function mimeType(string $path): string
    {
        return mime_content_type($this->fullPath($path)) ?: 'application/octet-stream';
    }

    public function url(string $path): string
    {
        $url = $this->config['url'] ?? '';

        return rtrim($url, '/') . '/' . ltrim($this->normalizePath($path), '/');
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        $path = $this->normalizePath($path);

        $timestamp = $expiration;

        if ($expiration < config('filesystems.links.threshold', 1000000000)) {
            $timestamp = time() + $expiration;
        }

        $signature = $this->generateSignature($path, $timestamp);

        $queryParams = http_build_query([
            'path' => $path,
            'expires' => $timestamp,
            'signature' => $signature,
        ]);

        $baseUrl = rtrim(config('host', ''), '/');

        $route = ltrim(config('filesystems.links.signed_route', '/storage/signed/view'), '/');

        return "{$baseUrl}/{$route}?{$queryParams}";
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->fullPath($directory);
        if (!FileSystem::isDir($fullPath)) {
            return [];
        }

        $files = [];
        $contents = FileSystem::contents($fullPath);

        if ($contents) {
            foreach ($contents as $file) {
                if ($file->isFile()) {
                    $files[] = $this->getRelativePath($file->getPathname());
                }
            }
        }

        return $files;
    }

    public function makeDirectory(string $path): bool
    {
        return FileSystem::mkdir($this->fullPath($path), 0755, true);
    }

    public function deleteDirectory(string $path): bool
    {
        return FileSystem::delete($this->fullPath($path), false);
    }

    public function readStream(string $path, array $options = []): mixed
    {
        $fullPath = $this->fullPath($path);
        if (!FileSystem::exists($fullPath)) {
            return null;
        }

        $stream = fopen($fullPath, 'rb');

        if (isset($options['start']) && $options['start'] > 0) {
            fseek($stream, $options['start']);
        }

        return $stream;
    }

    public function writeStream(string $path, $resource, array $options = []): bool
    {
        $fullPath = $this->fullPath($path);
        $directory = dirname($fullPath);

        if (!FileSystem::isDir($directory)) {
            FileSystem::mkdir($directory, 0755, true);
        }

        $dest = fopen($fullPath, 'wb');
        if (!$dest) {
            return false;
        }

        if ($options['lock'] ?? false) {
            flock($dest, LOCK_EX);
        }

        $success = stream_copy_to_stream($resource, $dest);

        if ($options['lock'] ?? false) {
            flock($dest, LOCK_UN);
        }

        fclose($dest);

        return $success !== false;
    }

    protected function fullPath(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($this->normalizePath($path), '/');
    }

    protected function getRelativePath(string $path): string
    {
        $root = str_replace(DIRECTORY_SEPARATOR, '/', $this->root);
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        return ltrim(str_replace($root, '', $path), '/');
    }
}
