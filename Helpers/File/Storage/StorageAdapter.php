<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * The dynamic configuration for the adapter.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Storage;

use Exception;

abstract class StorageAdapter implements StorageInterface
{
    protected array $config = [];

    /**
     * Create a new adapter instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Normalize paths to be consistent.
     */
    protected function normalizePath(string $path): string
    {
        return ltrim(str_replace(['\\', '//'], '/', $path), '/');
    }

    public function readStream(string $path, array $options = []): mixed
    {
        $contents = $this->get($path);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function writeStream(string $path, $resource, array $options = []): bool
    {
        $contents = stream_get_contents($resource);

        return $this->put($path, $contents, $options);
    }

    protected function retry(callable $callback, int $times = 3, int $sleep = 100): mixed
    {
        $attempts = 0;

        do {
            try {
                return $callback();
            } catch (Exception $e) {
                $attempts++;
                if ($attempts >= $times) {
                    throw $e;
                }
                usleep($sleep * 1000);
            }
        } while ($attempts < $times);

        return false;
    }

    public function generateSignature(string $path, int $expiration): string
    {
        $key = config('encryption_key');
        if (! $key) {
            throw new Exception('Application key (encryption_key) is not set.');
        }

        $data = "{$path}::{$expiration}";

        return hash_hmac('sha256', $data, $key);
    }

    public function hasValidSignature(string $path, int $expiration, string $signature): bool
    {
        $expected = $this->generateSignature($path, $expiration);

        return hash_equals($expected, $signature);
    }
}
