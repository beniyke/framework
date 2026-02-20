<?php

declare(strict_types=1);

namespace Helpers\File\Storage;

/**
 * @method static bool             exists(string $path)
 * @method static string           get(string $path)
 * @method static bool             put(string $path, string $contents, array $options = [])
 * @method static bool             delete(string $path)
 * @method static bool             copy(string $from, string $to)
 * @method static bool             move(string $from, string $to)
 * @method static int              size(string $path)
 * @method static int              lastModified(string $path)
 * @method static string           mimeType(string $path)
 * @method static string           url(string $path)
 * @method static string           temporaryUrl(string $path, int $expiration, array $options = [])
 * @method static array            files(string $directory = '', bool $recursive = false)
 * @method static bool             makeDirectory(string $path)
 * @method static bool             deleteDirectory(string $path)
 * @method static StorageInterface disk(string $name = null)
 */
class Storage
{
    protected static function manager(): StorageManager
    {
        return resolve(StorageManager::class);
    }

    /**
     * Dynamically pass methods to the storage manager.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::manager()->{$method}(...$parameters);
    }
}
