<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * FileSystem provides a convenient and efficient way to interact with the file system.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class FileSystem
{
    public static function hash(string $path): string
    {
        $hash = md5_file($path);
        if ($hash === false) {
            throw new RuntimeException("Could not calculate hash for file at {$path}.");
        }

        return $hash;
    }

    /**
     * Determine if a file or directory exists.
     */
    public static function exists(string $filename): bool
    {
        return file_exists($filename);
    }

    /**
     * Determine if the given path is readable.
     */
    public static function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * Determine if the given path is a file.
     */
    public static function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * Determine if the given path is a directory.
     */
    public static function isDir(string $path): bool
    {
        return is_dir($path);
    }

    public static function size(string $path): int
    {
        return is_file($path) ? (filesize($path) ?: 0) : 0;
    }

    /**
     * Get the file's last modification time.
     */
    public static function lastModified(string $path): int
    {
        clearstatcache(false, $path);

        return filemtime($path);
    }

    /**
     * Get the contents of a file.
     */
    public static function get(string $path, bool $lock = false): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("File does not exist or is not a file at path {$path}.");
        }

        if (! $lock) {
            return file_get_contents($path) ?: '';
        }

        $content = '';
        $handle = fopen($path, 'rb');

        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    $content = stream_get_contents($handle);
                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $content ?: '';
    }

    /**
     * Write the contents of a file.
     */
    public static function put(string $path, string $content, bool $lock = false): bool
    {
        try {
            $flags = $lock ? LOCK_EX : 0;

            return file_put_contents($path, $content, $flags) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Write the contents of a file atomically to prevent race conditions.
     */
    public static function replace(string $path, string $content): bool
    {
        try {
            clearstatcache(true, $path);
            $dir = dirname($path);
            $base = basename($path);

            if (! is_dir($dir) || ! is_writable($dir)) {
                return false;
            }

            $temp = tempnam($dir, $base);
            $permissions = 0666 & ~umask();
            static::chmod($temp, $permissions);
            static::put($temp, $content, true);
            static::move($temp, $path);

            return true;
        } catch (Exception $e) {
            if (isset($temp) && is_file($temp)) {
                @unlink($temp);
            }

            return false;
        }
    }

    /**
     * Prepend content to a file.
     */
    public static function prepend(string $path, string $data): bool
    {
        $content = '';
        if (file_exists($path)) {
            try {
                $content = static::get($path);
            } catch (Exception $e) {
                error_log('Error reading file to prepend to: ' . $e->getMessage());
            }
        }

        $data .= $content;

        return static::put($path, $data);
    }

    /**
     * Append content to a file.
     */
    public static function append(string $path, string $data): bool
    {
        try {
            return file_put_contents($path, $data, FILE_APPEND | LOCK_EX) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Change the mode (permissions) of a file or directory.
     */
    public static function chmod(string $path, int $permission): bool
    {
        return chmod($path, $permission);
    }

    public static function permissions(string $path): string
    {
        return mb_substr(sprintf('%o', fileperms($path)), -4);
    }

    /**
     * Delete a file or recursively delete a directory.
     *
     * @param string $path     File or directory path.
     * @param bool   $preserve If true, only delete contents of a directory, not the directory itself.
     */
    public static function delete(string $path, bool $preserve = false): bool
    {
        if (is_file($path)) {
            return @unlink($path);
        }

        if (is_dir($path)) {
            $success = true;
            $items = new FilesystemIterator($path);

            foreach ($items as $item) {
                $pathname = $item->getPathname();

                if ($item->isLink()) {
                    @unlink($pathname);

                    continue;
                }

                if ($item->isFile()) {
                    if (! @unlink($pathname)) {
                        $success = false;
                    }
                } elseif ($item->isDir()) {
                    if (! static::delete($pathname)) {
                        $success = false;
                    }
                }
            }

            unset($items);

            if ($success && ! $preserve) {
                return @rmdir($path);
            }

            return $success;
        }

        return false;
    }

    /**
     * Move a file or directory.
     */
    public static function move(string $path, string $target): bool
    {
        return rename($path, $target);
    }

    /**
     * Copy a file or directory.
     */
    public static function copy(string $directory, string $destination, ?int $flag = null): bool
    {
        if (! is_dir($directory) && ! is_file($directory)) {
            return false;
        }

        if (is_file($directory)) {
            return copy($directory, $destination);
        }

        $flag = $flag ?? FilesystemIterator::SKIP_DOTS;

        if (! is_dir($destination)) {
            if (! static::mkdir($destination, 0755, true)) {
                return false;
            }
        }

        $items = new FilesystemIterator($directory, $flag);

        foreach ($items as $item) {
            $target = $destination . '/' . $item->getBasename();

            if ($item->isDir()) {
                if (! static::copy($item->getPathname(), $target, $flag)) {
                    return false;
                }
            } else {
                if (! copy($item->getPathname(), $target)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create a directory.
     */
    public static function mkdir(string $path, int $permission = 0755, bool $recursive = true): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (is_file($path)) {
            return false;
        }

        return mkdir($path, $permission, $recursive);
    }

    public static function contents(string $path, ?int $flag = null): ?RecursiveIteratorIterator
    {
        if (! is_dir($path)) {
            return null;
        }

        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, $flag ?? RecursiveDirectoryIterator::SKIP_DOTS)
        );
    }

    public static function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Read the contents of a file.
     */
    public static function read(string $path): string
    {
        return static::get($path);
    }

    /**
     * Write the contents to a file.
     */
    public static function write(string $path, string $content): bool
    {
        return static::put($path, $content);
    }
}
