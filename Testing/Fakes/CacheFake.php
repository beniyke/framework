<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Fake Cache for testing cache operations.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Fakes;

use Helpers\File\Contracts\CacheInterface;
use PHPUnit\Framework\Assert as PHPUnit;

class CacheFake implements CacheInterface
{
    /**
     * The in-memory cache storage.
     *
     * @var array<string, mixed>
     */
    protected array $store = [];

    /**
     * Record of cache operations.
     *
     * @var array<int, array{operation: string, key: string, value?: mixed}>
     */
    protected array $operations = [];

    /**
     * The current sub-path.
     */
    protected string $subPath = '';

    public function write(string $key, mixed $value, int $ttl = 0): bool
    {
        $fullKey = $this->buildKey($key);
        $this->store[$fullKey] = [
            'value' => $value,
            'expires' => $ttl !== 0 ? time() + $ttl : null,
        ];

        $this->operations[] = [
            'operation' => 'write',
            'key' => $fullKey,
            'value' => $value,
        ];

        return true;
    }

    public function read(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->buildKey($key);

        $this->operations[] = [
            'operation' => 'read',
            'key' => $fullKey,
        ];

        if (! isset($this->store[$fullKey])) {
            return $default;
        }

        $item = $this->store[$fullKey];

        if ($item['expires'] !== null && $item['expires'] < time()) {
            unset($this->store[$fullKey]);

            return $default;
        }

        return $item['value'];
    }

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        $fullKey = $this->buildKey($key);

        if (! isset($this->store[$fullKey])) {
            return false;
        }

        $item = $this->store[$fullKey];

        if ($item['expires'] !== null && $item['expires'] < time()) {
            unset($this->store[$fullKey]);

            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $fullKey = $this->buildKey($key);

        $this->operations[] = [
            'operation' => 'delete',
            'key' => $fullKey,
        ];

        unset($this->store[$fullKey]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        $this->operations[] = [
            'operation' => 'clear',
            'key' => '*',
        ];

        return true;
    }

    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->read($key);
        }

        $value = $callback();
        $this->write($key, $value, $seconds);

        return $value;
    }

    public function permanent(string $key, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->read($key);
        }

        $value = $callback();
        $this->write($key, $value, 0);

        return $value;
    }

    /**
     * Create a new instance with a sub-path.
     */
    public function withPath(string $subPath): self
    {
        $clone = clone $this;
        $clone->subPath = $subPath;

        return $clone;
    }

    /**
     * Get all keys.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->store);
    }

    /**
     * Assert that a key was written.
     */
    public function assertWritten(string $key, mixed $value = null): void
    {
        $writes = array_filter($this->operations, function ($op) use ($key) {
            return $op['operation'] === 'write' && str_ends_with($op['key'], $key);
        });

        PHPUnit::assertTrue(
            count($writes) > 0,
            "The expected cache key [{$key}] was not written."
        );

        if ($value !== null) {
            $lastWrite = end($writes);
            PHPUnit::assertEquals(
                $value,
                $lastWrite['value'],
                "The cache key [{$key}] was written with unexpected value."
            );
        }
    }

    /**
     * Assert that a key was read.
     */
    public function assertRead(string $key): void
    {
        $reads = array_filter($this->operations, function ($op) use ($key) {
            return $op['operation'] === 'read' && str_ends_with($op['key'], $key);
        });

        PHPUnit::assertTrue(
            count($reads) > 0,
            "The expected cache key [{$key}] was not read."
        );
    }

    /**
     * Assert that a key was deleted.
     */
    public function assertDeleted(string $key): void
    {
        $deletes = array_filter($this->operations, function ($op) use ($key) {
            return $op['operation'] === 'delete' && str_ends_with($op['key'], $key);
        });

        PHPUnit::assertTrue(
            count($deletes) > 0,
            "The expected cache key [{$key}] was not deleted."
        );
    }

    /**
     * Assert that the cache was cleared.
     */
    public function assertCleared(): void
    {
        $clears = array_filter($this->operations, function ($op) {
            return $op['operation'] === 'clear';
        });

        PHPUnit::assertTrue(
            count($clears) > 0,
            'The cache was not cleared.'
        );
    }

    /**
     * Assert cache has a key with optional value check.
     */
    public function assertHas(string $key, mixed $value = null): void
    {
        $fullKey = $this->buildKey($key);

        PHPUnit::assertTrue(
            $this->has($key),
            "The cache does not have key [{$key}]."
        );

        if ($value !== null) {
            PHPUnit::assertEquals(
                $value,
                $this->read($key),
                "The cache key [{$key}] does not have expected value."
            );
        }
    }

    /**
     * Assert cache does not have a key.
     */
    public function assertMissing(string $key): void
    {
        PHPUnit::assertFalse(
            $this->has($key),
            "The cache unexpectedly has key [{$key}]."
        );
    }

    /**
     * Build the full key with sub-path.
     */
    protected function buildKey(string $key): string
    {
        return $this->subPath ? "{$this->subPath}/{$key}" : $key;
    }

    /**
     * Clear the operation log.
     */
    public function clearOperations(): void
    {
        $this->operations = [];
    }
}
