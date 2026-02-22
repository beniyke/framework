<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class ZipAdapter implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Storage\Adapters;

use Helpers\File\Storage\StorageAdapter;
use RuntimeException;
use ZipArchive;

class ZipAdapter extends StorageAdapter
{
    protected string $path;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->path = $config['path'] ?? '';

        if (empty($this->path)) {
            throw new RuntimeException('Zip storage requires a [path] to the zip file.');
        }
    }

    protected function open(int $flags = 0): ZipArchive
    {
        $zip = new ZipArchive();
        $result = $zip->open($this->path, $flags);

        if ($result !== true) {
            throw new RuntimeException("Could not open zip file [{$this->path}]. Error code: {$result}");
        }

        return $zip;
    }

    public function exists(string $path): bool
    {
        if (!file_exists($this->path)) {
            return false;
        }

        $zip = $this->open();
        $exists = $zip->locateName($this->normalizePath($path)) !== false;
        $zip->close();

        return $exists;
    }

    public function get(string $path): string
    {
        if (!file_exists($this->path)) {
            return '';
        }

        $zip = $this->open();
        $contents = $zip->getFromName($this->normalizePath($path));
        $zip->close();

        return $contents === false ? '' : $contents;
    }

    public function put(string $path, string $contents, array $options = []): bool
    {
        $zip = $this->open(ZipArchive::CREATE);
        $result = $zip->addFromString($this->normalizePath($path), $contents);
        $zip->close();

        return $result;
    }

    public function delete(string $path): bool
    {
        if (!file_exists($this->path)) {
            return true; // Consider deleted if archive doesn't exist
        }

        $zip = $this->open();
        $result = $zip->deleteName($this->normalizePath($path));
        $zip->close();

        return $result;
    }

    public function copy(string $from, string $to): bool
    {
        $contents = $this->get($from);
        if ($contents !== '') {
            return $this->put($to, $contents);
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
        $zip = $this->open();
        $stat = $zip->statName($this->normalizePath($path));
        $zip->close();

        return $stat ? $stat['size'] : 0;
    }

    public function lastModified(string $path): int
    {
        $zip = $this->open();
        $stat = $zip->statName($this->normalizePath($path));
        $zip->close();

        return $stat ? $stat['mtime'] : 0;
    }

    public function mimeType(string $path): string
    {
        return 'application/octet-stream';
    }

    public function url(string $path): string
    {
        return 'zip://' . $this->path . '#' . $this->normalizePath($path);
    }

    public function temporaryUrl(string $path, int $expiration, array $options = []): string
    {
        return $this->url($path);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $zip = $this->open();
        $directory = $this->normalizePath($directory);
        $files = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($directory === '' || str_starts_with($name, $directory . '/')) {
                if (!$recursive) {
                    $relative = $directory === '' ? $name : substr($name, strlen($directory) + 1);
                    if (strpos($relative, '/') === false) {
                        $files[] = $name;
                    }
                } else {
                    $files[] = $name;
                }
            }
        }

        $zip->close();

        return $files;
    }

    public function makeDirectory(string $path): bool
    {
        $zip = $this->open(ZipArchive::CREATE);
        $result = $zip->addEmptyDir($this->normalizePath($path));
        $zip->close();

        return $result;
    }

    public function deleteDirectory(string $path): bool
    {
        $zip = $this->open();
        $directory = $this->normalizePath($path) . '/';
        $toDelete = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, $directory)) {
                $toDelete[] = $name;
            }
        }

        foreach ($toDelete as $name) {
            $zip->deleteName($name);
        }

        $zip->deleteName(rtrim($directory, '/'));
        $zip->close();

        return true;
    }
}
