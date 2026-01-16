<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Contract for cache operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Contracts;

interface CacheInterface
{
    /**
     * Writes data to the cache file. Uses atomic replacement for safety.
     */
    public function write(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Reads data from the cache file.
     */
    public function read(string $key): mixed;

    /**
     * Checks if a key exists and is not expired.
     */
    public function has(string $key): bool;

    public function delete(string $key): bool;

    /**
     * Clears all cache files in the directory managed by this instance.
     */
    public function clear(): bool;

    /**
     * Retrieves an item from the cache or stores it (using the callback) if it doesn't exist or is expired.
     */
    public function remember(string $key, int $seconds, callable $callback): mixed;

    /**
     * Retrieves a permanent item from the cache or stores it (using the callback) if it doesn't exist.
     */
    public function permanent(string $key, callable $callback): mixed;

    /**
     * Create a new cache instance with a sub-path.
     */
    public function withPath(string $subPath): self;

    public function keys(): array;
}
